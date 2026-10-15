<?php

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function inventoryCsv(array $lines): UploadedFile
{
    $handle = fopen('php://temp', 'w+');
    foreach ($lines as $line) {
        fputcsv($handle, $line, escape: '');
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    return UploadedFile::fake()->createWithContent('inventory.csv', $content);
}

function inventoryHeaders(): array
{
    return ['sku', 'name', 'category', 'brand', 'purchase_price', 'selling_price', 'opening_stock', 'minimum_stock', 'unit', 'supplier', 'storage_location', 'is_active'];
}

function inventoryRow(string $sku = 'OLI-01', int $stock = 5): array
{
    return [$sku, 'Oli motor', 'Pelumas', 'AJM', '25000.00', '35000.00', $stock, 2, 'botol', '', '', '1'];
}

it('imports a csv product with an audited opening stock and exports both formats', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->post(route('inventory.import'), ['file' => inventoryCsv([inventoryHeaders(), inventoryRow()])])
        ->assertRedirect(route('inventory.index'))->assertSessionHas('inventoryStatus');
    $item = InventoryItem::where('sku', 'OLI-01')->sole();
    expect($item->current_stock)->toBe(5)->and($item->selling_price)->toBe('35000.00')
        ->and($item->category->name)->toBe('Pelumas')
        ->and(StockMovement::where('inventory_item_id', $item->id)->where('quantity', 5)->count())->toBe(1);
    $this->get(route('inventory.export', ['format' => 'csv']))->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertStreamedContent("sku,name,category,brand,purchase_price,selling_price,opening_stock,minimum_stock,unit,supplier,storage_location,is_active\nOLI-01,\"Oli motor\",Pelumas,AJM,25000.00,35000.00,5,2,botol,,,1\n");
    $xlsx = $this->get(route('inventory.export', ['format' => 'xlsx']))->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->streamedContent();
    $path = tempnam(sys_get_temp_dir(), 'export');
    try {
        file_put_contents($path, $xlsx);
        $book = IOFactory::load($path);
        expect($book->getActiveSheet()->getCell('A2')->getValue())->toBe('OLI-01')
            ->and($book->getActiveSheet()->getCell('G2')->getValue())->toBe('5');
        $book->disconnectWorksheets();
    } finally {
        unlink($path);
    }
});

it('provides visible CSV and XLSX controls and a guarded import form', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('inventory.index'))->assertOk()
        ->assertSee(route('inventory.import'), false)
        ->assertSee(route('inventory.export', ['format' => 'csv', 'template' => 1]))
        ->assertSee(route('inventory.export', ['format' => 'xlsx', 'template' => 1]))
        ->assertSee('Unduh CSV')->assertSee('Unduh XLSX')
        ->assertSee('Impor produk')->assertSee('SKU yang sudah ada ditolak');
});

it('keeps import and export choices behind two inventory controls', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('inventory.index'))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//details[@data-inventory-import]/summary[normalize-space()="Impor"]')->length)->toBe(1)
        ->and($xpath->query('//details[@data-inventory-export]/summary[normalize-space()="Ekspor"]')->length)->toBe(1)
        ->and($xpath->query('//details[@data-inventory-import]//form[@action="'.route('inventory.import').'"]')->length)->toBe(1)
        ->and($xpath->query('//details[@data-inventory-import]//a[contains(@href, "template=1")]')->length)->toBe(2)
        ->and($xpath->query('//details[@data-inventory-export]//a[contains(@href, "template=1")]')->length)->toBe(0)
        ->and($xpath->query('//details[@data-inventory-export]//a[contains(text(), "Unduh")]')->length)->toBe(2);
});

it('makes the import file picker a visible labelled box', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('inventory.index'))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//details[@data-inventory-import]//label[@for="inventory-file" and contains(@class, "border") and contains(., "Pilih file CSV atau XLSX")]')->length)->toBe(1)
        ->and($xpath->query('//details[@data-inventory-import]//input[@id="inventory-file" and @name="file" and @type="file" and @required and @accept=".csv,.xlsx"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="inventory-file-help" and contains(., "2 MB")]')->length)->toBe(1);
});

it('rejects duplicate sku including archived products and rolls back every row', function (bool $archived) {
    $item = InventoryItem::factory()->create(['sku' => 'TAKEN']);
    if ($archived) {
        $item->delete();
    }
    $this->actingAs(User::factory()->create(['role' => 'owner']))
        ->post(route('inventory.import'), ['file' => inventoryCsv([inventoryHeaders(), inventoryRow('NEW'), inventoryRow('TAKEN')])])
        ->assertSessionHasErrors('file');
    expect(InventoryItem::withTrashed()->count())->toBe(1)
        ->and(StockMovement::count())->toBe(0);
})->with([false, true]);

it('escapes spreadsheet formulas in CSV and exports XLSX text cells safely', function () {
    $item = InventoryItem::factory()->create(['sku' => '=1+1', 'name' => '+HYPERLINK("https://bad.invalid")', 'current_stock' => 0]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $csv = $this->get(route('inventory.export', ['format' => 'csv']))->assertOk()->streamedContent();
    expect($csv)->toContain("'=1+1")->toContain("'+HYPERLINK");
    $xlsx = $this->get(route('inventory.export', ['format' => 'xlsx']))->assertOk()->streamedContent();
    $path = tempnam(sys_get_temp_dir(), 'safexlsx');
    try {
        file_put_contents($path, $xlsx);
        $book = IOFactory::load($path);
        expect($book->getActiveSheet()->getCell('A2')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and($book->getActiveSheet()->getCell('A2')->getValue())->toBe('=1+1');
        $book->disconnectWorksheets();
    } finally {
        unlink($path);
    }
});

it('rejects existing SKU with different letter case before writing anything', function () {
    InventoryItem::factory()->create(['sku' => 'OLI-A']);
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('inventory.import'), ['file' => inventoryCsv([inventoryHeaders(), inventoryRow('oli-a')])])
        ->assertSessionHasErrors('file');
    expect(InventoryItem::count())->toBe(1)
        ->and(StockMovement::count())->toBe(0);
});

it('reports each existing SKU only once in Indonesian', function () {
    InventoryItem::factory()->create(['sku' => 'TAKEN']);
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('inventory.import'), ['file' => inventoryCsv([inventoryHeaders(), inventoryRow('TAKEN'), inventoryRow('TAKEN')])])
        ->assertSessionHasErrors('file');
    $messages = $response->baseResponse->getSession()->get('errors')->get('file');
    expect($messages)->toBe([
        'Baris 2: SKU sudah ada, termasuk arsip (TAKEN).',
        'Baris 3: SKU duplikat dengan baris 2 (TAKEN).',
    ]);
    expect(InventoryItem::count())->toBe(1);
});

it('rejects a duplicate inside the file without partial writes', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('inventory.import'), ['file' => inventoryCsv([inventoryHeaders(), inventoryRow('DUP'), inventoryRow('DUP')])])
        ->assertSessionHasErrors('file');
    expect(InventoryItem::count())->toBe(0);
});

it('reports invalid spreadsheet fields in Indonesian', function () {
    $row = inventoryRow('NEW');
    $row[1] = '';
    $row[4] = '-1';
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('inventory.import'), ['file' => inventoryCsv([inventoryHeaders(), $row])])
        ->assertSessionHasErrors('file');
    $messages = $response->baseResponse->getSession()->get('errors')->get('file');
    expect($messages)->toContain('Baris 2 · nama barang: Wajib diisi.', 'Baris 2 · harga beli: Format tidak valid.');
    expect(InventoryItem::count())->toBe(0);
});

it('reports every invalid CSV row and field while leaving the database untouched', function () {
    InventoryItem::factory()->create(['sku' => 'TAKEN']);
    $invalidPrice = inventoryRow('BAD-PRICE');
    $invalidPrice[4] = '-1';
    $invalidPrice[6] = -3;
    $invalidName = inventoryRow('TAKEN');
    $invalidName[1] = '';
    $invalidUnit = inventoryRow('BAD-UNIT');
    $invalidUnit[8] = '';
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('inventory.import'), ['file' => inventoryCsv([inventoryHeaders(), $invalidPrice, $invalidName, $invalidUnit])])
        ->assertSessionHasErrors('file');
    $messages = $response->baseResponse->getSession()->get('errors')->get('file');
    expect($messages)->toHaveCount(5);
    expect(implode(' ', $messages))->toContain('Baris 2', 'harga beli', 'stok awal', 'Baris 3', 'SKU', 'nama barang', 'Baris 4', 'satuan');
    expect(implode(' ', $messages))->not->toContain('The sku has already been taken.');
    $response->assertSessionHas('inventory_import_errors', $messages);
    session()->flash('inventory_import_errors', $messages);
    $page = $this->get(route('inventory.index'))->assertOk()->assertSee('Impor dibatalkan');
    foreach ($messages as $message) {
        $page->assertSee($message);
    }
    expect(InventoryItem::count())->toBe(1)
        ->and(InventoryCategory::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);
});

it('reports every invalid XLSX row and field without importing valid rows', function () {
    $sheet = new Spreadsheet;
    $rows = [inventoryHeaders(), inventoryRow('VALID'), inventoryRow('BAD-PRICE'), inventoryRow('BAD-STOCK')];
    $rows[2][4] = '-1';
    $rows[3][6] = '-2';
    $sheet->getActiveSheet()->fromArray($rows);
    $path = tempnam(sys_get_temp_dir(), 'inventory');
    (new Xlsx($sheet))->save($path);
    try {
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('inventory.import'), ['file' => new UploadedFile($path, 'inventory.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)])
            ->assertSessionHasErrors('file');
        $errors = $response->baseResponse->getSession()->get('errors')->get('file');
        expect(implode(' ', $errors))->toContain('Baris 3', 'harga beli', 'Baris 4', 'stok awal');
        expect(InventoryItem::count())->toBe(0);
    } finally {
        $sheet->disconnectWorksheets();
        unlink($path);
    }
});

it('accepts an ordinary CSV with a trailing empty line', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $handle = fopen('php://temp', 'w+');
    fputcsv($handle, inventoryHeaders(), escape: '');
    fputcsv($handle, inventoryRow('BLANK-LINE'), escape: '');
    fwrite($handle, "\n");
    rewind($handle);
    $file = UploadedFile::fake()->createWithContent('inventory.csv', stream_get_contents($handle));
    fclose($handle);

    $this->post(route('inventory.import'), ['file' => $file])->assertSessionHasNoErrors();
    expect(InventoryItem::where('sku', 'BLANK-LINE')->sole()->current_stock)->toBe(5);
});

it('reports invalid stock on the upload field and rolls back earlier products and categories', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->post(route('inventory.import'), ['file' => inventoryCsv([inventoryHeaders(), inventoryRow('GOOD'), inventoryRow('BAD', -1)])])
        ->assertSessionHasErrors('file');
    expect(InventoryItem::count())->toBe(0)
        ->and(InventoryCategory::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);
});

it('rejects a compressed xlsx with oversized extracted contents', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $sheet = new Spreadsheet;
    $sheet->getActiveSheet()->fromArray([inventoryHeaders(), inventoryRow('ZIP-BOMB')]);
    $path = tempnam(sys_get_temp_dir(), 'inventory');
    (new Xlsx($sheet))->save($path);
    $padding = tempnam(sys_get_temp_dir(), 'padding');
    $handle = fopen($padding, 'wb');
    for ($index = 0; $index < 21; $index++) {
        fwrite($handle, str_repeat('x', 1024 * 1024));
    }
    fclose($handle);
    $zip = new ZipArchive;
    $zip->open($path);
    $zip->addFile($padding, 'xl/media/padding.txt');
    $zip->close();
    try {
        $this->post(route('inventory.import'), ['file' => new UploadedFile($path, 'inventory.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)])
            ->assertSessionHasErrors('file');
        expect(InventoryItem::count())->toBe(0);
    } finally {
        $sheet->disconnectWorksheets();
        unlink($path);
        unlink($padding);
    }
});

it('rejects unexpected xlsx columns rather than silently ignoring values', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $sheet = new Spreadsheet;
    $sheet->getActiveSheet()->fromArray([inventoryHeaders(), inventoryRow('EXTRA')]);
    $sheet->getActiveSheet()->setCellValue('M2', 'unexpected');
    $path = tempnam(sys_get_temp_dir(), 'inventory');
    (new Xlsx($sheet))->save($path);
    try {
        $this->post(route('inventory.import'), ['file' => new UploadedFile($path, 'inventory.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)])
            ->assertSessionHasErrors('file');
        expect(InventoryItem::count())->toBe(0);
    } finally {
        $sheet->disconnectWorksheets();
        unlink($path);
    }
});

it('rejects spreadsheet formulas without writing products', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $sheet = new Spreadsheet;
    $sheet->getActiveSheet()->fromArray([inventoryHeaders(), inventoryRow('CLEAN'), inventoryRow('FORMULA')]);
    $sheet->getActiveSheet()->setCellValue('B3', '=2+2');
    $path = tempnam(sys_get_temp_dir(), 'inventory');
    (new Xlsx($sheet))->save($path);
    try {
        $this->post(route('inventory.import'), ['file' => new UploadedFile($path, 'inventory.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)])
            ->assertSessionHasErrors('file');
        expect(InventoryItem::count())->toBe(0);
    } finally {
        $sheet->disconnectWorksheets();
        unlink($path);
    }
});

it('imports real xlsx, rejecting formulas and preserving atomicity', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $sheet = new Spreadsheet;
    $sheet->getActiveSheet()->fromArray([inventoryHeaders(), inventoryRow('XLS-01')]);
    $path = tempnam(sys_get_temp_dir(), 'inventory');
    (new Xlsx($sheet))->save($path);
    try {
        $this->post(route('inventory.import'), ['file' => new UploadedFile($path, 'inventory.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)])
            ->assertRedirect(route('inventory.index'))->assertSessionHas('inventoryStatus');
        expect(InventoryItem::where('sku', 'XLS-01')->sole()->current_stock)->toBe(5);
    } finally {
        $sheet->disconnectWorksheets();
        unlink($path);
    }
});

it('rejects malformed export formats without a server error', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('inventory.export', ['format' => ['csv']]))->assertNotFound();
});

it('restricts import and export to workshop managers', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));
    $this->get(route('inventory.export', ['format' => 'csv']))->assertForbidden();
    $this->post(route('inventory.import'), ['file' => inventoryCsv([inventoryHeaders(), inventoryRow()])])->assertForbidden();
    expect(InventoryItem::count())->toBe(0);
})->with(['mechanic', 'customer']);
