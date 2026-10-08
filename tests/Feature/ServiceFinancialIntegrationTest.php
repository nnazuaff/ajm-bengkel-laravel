<?php

use App\Actions\StockLedger;
use App\Actions\UpdateServiceOrder;
use App\Actions\UseServicePart;
use App\Models\InventoryItem;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\User;

it('returns service stock exactly once during cancellation and preserves movement lineage', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $item = InventoryItem::factory()->create(['current_stock' => 0, 'selling_price' => '25000.00']);
    app(StockLedger::class)->move($actor, $item, 5, 'in', 'purchase');
    $order = ServiceOrder::factory()->create(['status' => 'inspection']);
    $part = app(UseServicePart::class)->use($actor, $order, $item->id, 2);
    expect($item->fresh()->current_stock)->toBe(3);
    app(UpdateServiceOrder::class)->update($actor, $order, ['status' => 'cancelled', 'notes' => 'Pelanggan membatalkan.']);
    expect($item->fresh()->current_stock)->toBe(5)->and($part->fresh()->returned_at)->not->toBeNull();
    $before = StockMovement::count();
    app(UseServicePart::class)->returnAll($actor, $order);
    expect(StockMovement::count())->toBe($before)->and($item->fresh()->current_stock)->toBe(5);
});
