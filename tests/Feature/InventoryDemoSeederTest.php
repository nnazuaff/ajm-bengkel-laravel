<?php

use App\Actions\StockLedger;
use App\Models\AuditLog;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\InventoryDemoSeeder;

it('seeds recognizable demo inventory and ledger without resetting existing data on rerun', function () {
    $actor = User::factory()->create(['role' => 'owner']);
    $existing = InventoryItem::factory()->create(['sku' => 'REAL-001', 'name' => 'Barang asli', 'current_stock' => 7]);
    $this->seed(InventoryDemoSeeder::class);
    expect(InventoryItem::where('sku', 'like', 'DEMO-%')->count())->toBe(12)
        ->and(InventoryCategory::where('name', 'like', '[DEMO]%')->count())->toBe(6)
        ->and(StockMovement::count())->toBe(10);
    expect(InventoryItem::where('sku', 'DEMO-OLI-001')->sole()->current_stock)->toBe(24);
    $used = InventoryItem::where('sku', 'DEMO-OLI-001')->sole();
    app(StockLedger::class)->move($actor, $used, -1, 'out', 'Test pemakaian');
    $used->name = 'Demo sudah diedit';
    $used->save();
    $used->delete();
    $this->seed(InventoryDemoSeeder::class);
    expect(InventoryItem::withTrashed()->where('sku', 'like', 'DEMO-%')->count())->toBe(12)
        ->and(StockMovement::count())->toBe(11)
        ->and($used->fresh()->current_stock)->toBe(23)
        ->and($used->fresh()->name)->toBe('Demo sudah diedit')
        ->and($used->fresh()->trashed())->toBeTrue()
        ->and($existing->fresh()->current_stock)->toBe(7);
});

it('requires an existing manager rather than creating a demo login', function () {
    User::factory()->create(['role' => 'customer']);
    expect(fn () => $this->seed(InventoryDemoSeeder::class))->toThrow(RuntimeException::class);
    expect(InventoryItem::count())->toBe(0)->and(InventoryCategory::count())->toBe(0);
});

it('rolls all demo inventory back if audit fails', function () {
    User::factory()->create(['role' => 'admin']);
    AuditLog::creating(fn () => throw new RuntimeException('Audit failed'));
    try {
        expect(fn () => $this->seed(InventoryDemoSeeder::class))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(InventoryItem::count())->toBe(0)->and(InventoryCategory::count())->toBe(0)->and(StockMovement::count())->toBe(0);
});
