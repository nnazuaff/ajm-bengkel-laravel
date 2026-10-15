<?php

namespace App\Support;

use App\Actions\SaveInventoryItem;
use App\Actions\StockLedger;
use App\Http\Controllers\ReportExportController;
use App\Models\AuditLog;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventorySpreadsheet
{
    public const HEADERS = ['sku', 'name', 'category', 'brand', 'purchase_price', 'selling_price', 'opening_stock', 'minimum_stock', 'unit', 'supplier', 'storage_location', 'is_active'];

    public const MAX_ROWS = 500;

    /** @return list<array<int, string>> */
    public function read(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['file' => 'Gunakan berkas CSV atau XLSX.']);
        }
        if ($extension === 'csv') {
            $handle = fopen($file->getRealPath(), 'rb');
            if ($handle === false) {
                throw ValidationException::withMessages(['file' => 'Berkas tidak dapat dibaca.']);
            }
            try {
                $rows = [];
                while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                    if (count($rows) > self::MAX_ROWS) {
                        throw ValidationException::withMessages(['file' => 'Maksimal '.self::MAX_ROWS.' produk per impor.']);
                    }
                    $rows[] = $row;
                }
                while (count($rows) > 1 && in_array(end($rows), [[null], ['']], true)) {
                    array_pop($rows);
                }
                if (isset($rows[0][0])) {
                    $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
                }
            } finally {
                fclose($handle);
            }
        } else {
            $zip = new \ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                throw ValidationException::withMessages(['file' => 'Berkas XLSX tidak dapat dibaca.']);
            }
            try {
                if ($zip->numFiles > 100) {
                    throw ValidationException::withMessages(['file' => 'Berkas XLSX terlalu besar.']);
                }
                $bytes = 0;
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $entry = $zip->statIndex($index);
                    if ($entry === false) {
                        throw ValidationException::withMessages(['file' => 'Berkas XLSX tidak dapat dibaca.']);
                    }
                    $bytes += $entry['size'];
                    if ($bytes > 20 * 1024 * 1024) {
                        throw ValidationException::withMessages(['file' => 'Berkas XLSX terlalu besar.']);
                    }
                }
            } finally {
                $zip->close();
            }
            try {
                $reader = IOFactory::createReader('Xlsx');
                $worksheets = $reader->listWorksheetInfo($file->getRealPath());
                if (count($worksheets) !== 1 || $worksheets[0]['totalColumns'] > count(self::HEADERS) || $worksheets[0]['totalRows'] > self::MAX_ROWS + 1) {
                    throw ValidationException::withMessages(['file' => 'Gunakan satu sheet dengan maksimal 12 kolom dan '.self::MAX_ROWS.' produk.']);
                }
                $reader->setReadFilter(new class implements IReadFilter
                {
                    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                    {
                        return $row <= InventorySpreadsheet::MAX_ROWS + 2 && in_array($columnAddress, range('A', 'L'), true);
                    }
                });
                $reader->setLoadSheetsOnly([$reader->listWorksheetNames($file->getRealPath())[0]]);
                $book = $reader->load($file->getRealPath());
                try {
                    $sheet = $book->getActiveSheet();
                    if ($sheet->getHighestDataRow() > self::MAX_ROWS + 1) {
                        throw ValidationException::withMessages(['file' => 'Maksimal '.self::MAX_ROWS.' produk per impor.']);
                    }
                    $rows = [];
                    $formulaErrors = [];
                    for ($number = 1; $number <= $sheet->getHighestDataRow(); $number++) {
                        $row = [];
                        foreach (range('A', 'L') as $column) {
                            $cell = $sheet->getCell($column.$number);
                            if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                                $formulaErrors[] = 'Baris '.$number.' · '.$column.': rumus tidak boleh dipakai.';
                            }
                            $row[] = (string) ($cell->getValue() ?? '');
                        }
                        $rows[] = $row;
                    }
                    if ($formulaErrors !== []) {
                        throw ValidationException::withMessages(['file' => $formulaErrors]);
                    }
                } finally {
                    $book->disconnectWorksheets();
                }
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages(['file' => 'Berkas XLSX tidak dapat dibaca.']);
            }
        }
        if (! isset($rows[0]) || $rows[0] !== self::HEADERS) {
            throw ValidationException::withMessages(['file' => 'Kolom tidak sesuai template. Unduh template CSV atau XLSX terlebih dahulu.']);
        }
        array_shift($rows);
        if ($rows === [] || count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Isi 1–'.self::MAX_ROWS.' produk per impor.']);
        }

        return $rows;
    }

    /** @param list<array<int, string>> $rows */
    public function import(User $actor, array $rows): int
    {
        Gate::forUser($actor)->authorize('create', InventoryItem::class);
        if (count($rows) < 1 || count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Isi 1–'.self::MAX_ROWS.' produk per impor.']);
        }

        $errors = [];
        $seen = [];
        $prepared = [];
        foreach ($rows as $index => $row) {
            $number = $index + 2;
            if (count($row) !== count(self::HEADERS)) {
                $errors[] = 'Baris '.$number.': jumlah kolom tidak sesuai.';

                continue;
            }
            if (! mb_check_encoding(implode('', $row), 'UTF-8') || str_contains(implode('', $row), "\0")) {
                $errors[] = 'Baris '.$number.': teks tidak valid.';

                continue;
            }
            $data = array_combine(self::HEADERS, $row);
            $sku = trim($data['sku']);
            $key = mb_strtolower($sku);
            if ($sku !== '') {
                if (isset($seen[$key])) {
                    $errors[] = 'Baris '.$number.': SKU duplikat dengan baris '.$seen[$key].' ('.$sku.').';
                } elseif (InventoryItem::withTrashed()->whereRaw('LOWER(sku) = ?', [$key])->exists()) {
                    $errors[] = 'Baris '.$number.': SKU sudah ada, termasuk arsip ('.$sku.').';
                }
                $seen[$key] ??= $number;
            }
            $master = [
                'sku' => $sku, 'name' => trim($data['name']), 'category_id' => null,
                'brand' => $data['brand'] ?: null, 'purchase_price' => $data['purchase_price'],
                'selling_price' => $data['selling_price'], 'minimum_stock' => $data['minimum_stock'],
                'unit' => trim($data['unit']), 'supplier' => $data['supplier'] ?: null,
                'storage_location' => $data['storage_location'] ?: null, 'is_active' => $data['is_active'],
            ];
            $validator = Validator::make([...$master, 'category' => $data['category'], 'opening_stock' => $data['opening_stock']], [
                ...SaveInventoryItem::rules(),
                'sku' => ['required', 'string', 'max:60'], // SKU lama/duplikat sudah diperiksa di atas, termasuk arsip dan beda kapital.
                'category' => ['nullable', 'string', 'max:120'],
                'opening_stock' => ['required', 'integer', 'min:0', 'max:'.StockLedger::MAX_STOCK],
            ], [
                'required' => 'Wajib diisi.',
                'string' => 'Harus berupa teks.',
                'max.string' => 'Maksimal :max karakter.',
                'max.numeric' => 'Maksimal :max.',
                'regex' => 'Format tidak valid.',
                'integer' => 'Harus berupa bilangan bulat.',
                'min.numeric' => 'Minimal :min.',
                'boolean' => 'Gunakan 1 atau 0.',
                'exists' => 'Data tidak ditemukan.',
                'prohibited' => 'Tidak boleh diisi.',
            ]);
            if ($validator->fails()) {
                $labels = [
                    'sku' => 'SKU', 'name' => 'nama barang', 'category' => 'kategori', 'brand' => 'merek',
                    'purchase_price' => 'harga beli', 'selling_price' => 'harga jual',
                    'opening_stock' => 'stok awal', 'minimum_stock' => 'stok minimum', 'unit' => 'satuan',
                    'supplier' => 'pemasok', 'storage_location' => 'lokasi penyimpanan', 'is_active' => 'status aktif',
                ];
                foreach ($validator->errors()->messages() as $field => $messages) {
                    foreach ($messages as $message) {
                        $errors[] = 'Baris '.$number.' · '.($labels[$field] ?? $field).': '.$message;
                    }
                }
            }
            $prepared[] = ['number' => $number, 'master' => $master, 'category' => trim($data['category']), 'stock' => (int) $data['opening_stock']];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => $errors]);
        }

        return DB::transaction(function () use ($actor, $prepared): int {
            foreach ($prepared as $row) {
                $master = $row['master'];
                if ($row['category'] !== '') {
                    $category = InventoryCategory::firstOrCreate(['name' => $row['category']]);
                    $master['category_id'] = $category->id;
                    if ($category->wasRecentlyCreated) {
                        $audit = AuditLog::create(['actor_id' => $actor->id, 'action' => 'inventory_category.created', 'entity_type' => InventoryCategory::class, 'entity_id' => $category->id, 'context' => ['name' => $category->name]]);
                        if (! $audit->exists) {
                            throw new \RuntimeException('Audit kategori gagal disimpan.');
                        }
                    }
                }
                try {
                    $item = app(SaveInventoryItem::class)->save($actor, $master);
                    if ($row['stock'] > 0) {
                        app(StockLedger::class)->move($actor, $item, $row['stock'], 'in', 'Stok awal impor inventori');
                    }
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['file' => 'Baris '.$row['number'].': '.implode(' ', array_merge(...array_values($exception->errors())))]);
                }
            }

            return count($prepared);
        });
    }

    public function export(string $format, bool $template = false): StreamedResponse
    {
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(404);
        }
        $name = $template ? 'template-inventori' : 'inventori';
        $headers = ['Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'X-Content-Type-Options' => 'nosniff'];

        return response()->streamDownload(function () use ($format, $template): void {
            $rows = [self::HEADERS];
            if (! $template) {
                foreach (InventoryItem::with('category')->lazyById(200) as $item) {
                    $rows[] = [$item->sku, $item->name, $item->category_id === null ? '' : $item->category->name, $item->brand ?? '', $item->purchase_price, $item->selling_price, (string) $item->current_stock, (string) $item->minimum_stock, $item->unit, $item->supplier ?? '', $item->storage_location ?? '', $item->is_active ? '1' : '0'];
                }
            }
            if ($format === 'csv') {
                $handle = fopen('php://output', 'wb');
                if ($handle === false) {
                    throw new \RuntimeException('CSV tidak dapat ditulis.');
                }
                foreach ($rows as $row) {
                    fputcsv($handle, array_map(ReportExportController::csvCell(...), $row), escape: '');
                }
                fclose($handle);

                return;
            }
            $book = new Spreadsheet;
            try {
                $sheet = $book->getActiveSheet();
                $sheet->setTitle('Inventori');
                foreach ($rows as $index => $row) {
                    foreach ($row as $column => $value) {
                        $sheet->setCellValueExplicit([$column + 1, $index + 1], (string) $value, DataType::TYPE_STRING);
                    }
                }
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:L'.count($rows));
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, $name.'.'.$format, $headers);
    }
}
