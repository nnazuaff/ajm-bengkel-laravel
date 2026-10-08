<?php

namespace Database\Seeders;

use App\Actions\SaveInventoryItem;
use App\Actions\StockLedger;
use App\Models\AuditLog;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Data demo hanya untuk lingkungan local/testing.');
        }
        $actor = User::query()->whereIn('role', ['owner', 'admin'])->orderBy('id')->first();
        if (! $actor) {
            throw new \RuntimeException('Buat akun owner/admin terlebih dahulu melalui workshop:create-user.');
        }

        // Demo only: prices are fictitious; existing SKU records and stock are never reset.
        $rows = [
            ['OLI-001', 'Oli mesin 10W-30 0,8 L', 'Pelumas', '28000.00', '35000.00', 24, 5, 'botol', true],
            ['OLI-002', 'Oli gardan 120 ml', 'Pelumas', '10000.00', '15000.00', 3, 5, 'botol', true],
            ['REM-001', 'Kampas rem depan skutik', 'Pengereman', '35000.00', '50000.00', 8, 3, 'set', true],
            ['REM-002', 'Kampas rem belakang tromol', 'Pengereman', '30000.00', '45000.00', 0, 3, 'set', true],
            ['CVT-001', 'V-belt skutik 125 cc', 'CVT', '85000.00', '110000.00', 5, 2, 'pcs', true],
            ['CVT-002', 'Roller CVT 12 gram', 'CVT', '32000.00', '45000.00', 2, 3, 'set', true],
            ['LIS-001', 'Busi motor standar', 'Kelistrikan', '16000.00', '25000.00', 15, 5, 'pcs', true],
            ['LIS-002', 'Lampu depan halogen', 'Kelistrikan', '20000.00', '30000.00', 0, 2, 'pcs', true],
            ['FIL-001', 'Filter udara skutik', 'Filter', '25000.00', '40000.00', 7, 3, 'pcs', true],
            ['FIL-002', 'Filter oli motor', 'Filter', '12000.00', '20000.00', 1, 2, 'pcs', true],
            ['BAN-001', 'Ban tubeless 80/90-14', 'Ban', '160000.00', '200000.00', 4, 2, 'pcs', true],
            ['BAN-002', 'Ban dalam 70/90-17', 'Ban', '22000.00', '35000.00', 6, 2, 'pcs', false],
        ];
        $created = DB::transaction(function () use ($actor, $rows): int {
            $count = 0;
            foreach ($rows as [$code, $name, $group, $buy, $sell, $stock, $minimum, $unit, $active]) {
                $sku = 'DEMO-'.$code;
                if (InventoryItem::withTrashed()->where('sku', $sku)->exists()) {
                    continue;
                }
                $category = InventoryCategory::firstOrCreate(['name' => '[DEMO] '.$group]);
                if ($category->wasRecentlyCreated) {
                    $audit = AuditLog::create(['actor_id' => $actor->id, 'action' => 'inventory_category.created', 'entity_type' => InventoryCategory::class, 'entity_id' => $category->id, 'context' => ['name' => $category->name, 'demo' => true]]);
                    if (! $audit->exists) {
                        throw new \RuntimeException('Audit gagal disimpan.');
                    }
                }
                $item = app(SaveInventoryItem::class)->save($actor, [
                    'sku' => $sku, 'name' => '[DEMO] '.$name, 'category_id' => $category->id,
                    'brand' => 'Demo', 'purchase_price' => $buy, 'selling_price' => $sell,
                    'minimum_stock' => $minimum, 'unit' => $unit, 'supplier' => 'Pemasok demo',
                    'storage_location' => 'Rak demo', 'is_active' => $active,
                ]);
                if ($stock > 0) {
                    app(StockLedger::class)->move($actor, $item, $stock, 'in', 'Stok awal data dummy untuk pengujian');
                }
                $count++;
            }

            return $count;
        });
        $this->command->info($created.' barang demo baru ditambahkan; data yang sudah ada dipertahankan.');
    }
}
