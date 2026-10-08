<?php

use App\Actions\SaveInventoryItem;
use App\Actions\StockLedger;
use App\Actions\UseServicePart;
use App\Livewire\ServiceParts;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ServiceItem;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('provides an assigned mechanic part picker and return controls without editable prices', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id]);
    $item = app(SaveInventoryItem::class)->save($admin, ['sku' => 'UI', 'name' => 'Busi UI', 'purchase_price' => '1', 'selling_price' => '15000.25', 'minimum_stock' => 0, 'unit' => 'pcs', 'is_active' => true]);
    app(StockLedger::class)->move($admin, $item, 5, 'in', 'purchase');
    $this->actingAs($mechanic);
    $page = Livewire::test(ServiceParts::class, ['serviceOrderId' => $order->id])->assertSee('Part servis')
        ->set('search', 'Busi')->assertSee('Busi UI')->set('inventoryItemId', (string) $item->id)->set('quantity', '2')
        ->call('usePart')->assertHasNoErrors()->assertSee('30000,50');
    $part = ServiceItem::sole();
    $page->call('returnPart', $part->id)->assertHasNoErrors()->assertSee('Dikembalikan');
    expect($item->fresh()->current_stock)->toBe(5);
});

it('denies child access for unrelated mechanic or customer', function (string $role) {
    $order = ServiceOrder::factory()->create();
    $this->actingAs(User::factory()->create(['role' => $role]));
    Livewire::test(ServiceParts::class, ['serviceOrderId' => $order->id])->assertForbidden();
})->with(['mechanic', 'customer']);

it('rejects invalid quantity and insufficient stock without creating usage', function (int $quantity) {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $item = InventoryItem::factory()->create(['current_stock' => 2]);
    expect(fn () => app(UseServicePart::class)->use($admin, $order, $item->id, $quantity))->toThrow(ValidationException::class);
    expect($item->fresh()->current_stock)->toBe(2)->and(ServiceItem::count())->toBe(0)->and(StockMovement::count())->toBe(0);
})->with([-1, 0, 3, 2147483648]);

it('rejects new usage of inactive or archived stock but allows lifecycle reversals', function (bool $archived) {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $item = InventoryItem::factory()->create(['current_stock' => 2]);
    $part = app(UseServicePart::class)->use($admin, $order, $item->id, 1);
    $item->refresh()->update(['is_active' => false]);
    if ($archived) {
        $item->delete();
    }
    expect(fn () => app(UseServicePart::class)->use($admin, $order, $item->id, 1))->toThrow(ValidationException::class);
    app(UseServicePart::class)->returnPart($admin, $order, $part);
    expect(InventoryItem::withTrashed()->find($item->id)->current_stock)->toBe(2);
})->with([false, true]);

it('rejects usage on completed or closed service', function (string $status) {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create(['status' => $status]);
    $item = InventoryItem::factory()->create(['current_stock' => 2]);
    expect(fn () => app(UseServicePart::class)->use($admin, $order, $item->id, 1))->toThrow(ValidationException::class);
})->with(['completed', 'ready_for_pickup', 'delivered', 'cancelled']);

it('rejects part subtotal overflow before persisting anything', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $item = InventoryItem::factory()->create(['current_stock' => 2, 'selling_price' => '999999999999.99']);
    expect(fn () => app(UseServicePart::class)->use($admin, $order, $item->id, 2))->toThrow(ValidationException::class);
    expect($item->fresh()->current_stock)->toBe(2)->and(ServiceItem::count())->toBe(0);
});

it('rolls usage back if the stock audit fails', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $item = InventoryItem::factory()->create(['current_stock' => 2]);
    AuditLog::creating(fn () => throw new RuntimeException('audit unavailable'));
    try {
        expect(fn () => app(UseServicePart::class)->use($admin, $order, $item->id, 1))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($item->fresh()->current_stock)->toBe(2)->and(ServiceItem::count())->toBe(0)->and(StockMovement::count())->toBe(0);
});

it('rolls a full return back when its audit fails', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $item = InventoryItem::factory()->create(['current_stock' => 2]);
    $part = app(UseServicePart::class)->use($admin, $order, $item->id, 1);
    AuditLog::creating(function (AuditLog $log): void {
        if ($log->getAttribute('action') === 'service_part.returned') {
            throw new RuntimeException('audit unavailable');
        }
    });
    try {
        expect(fn () => app(UseServicePart::class)->returnPart($admin, $order, $part))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($item->fresh()->current_stock)->toBe(1)->and($part->fresh()->returned_at)->toBeNull()->and(StockMovement::where('type', 'return')->count())->toBe(0);
});

it('rejects another orders part and unauthorized actor', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create();
    $other = ServiceOrder::factory()->create();
    $item = InventoryItem::factory()->create(['current_stock' => 2]);
    $part = app(UseServicePart::class)->use($admin, $order, $item->id, 1);
    expect(fn () => app(UseServicePart::class)->returnPart($admin, $other, $part))->toThrow(AuthorizationException::class);
    expect(fn () => app(UseServicePart::class)->use($mechanic, $order, $item->id, 1))->toThrow(AuthorizationException::class);
    expect(fn () => app(UseServicePart::class)->returnAll($mechanic, $order))->toThrow(AuthorizationException::class);
});

it('blocks all stock alterations when a final receipt exists', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $item = app(SaveInventoryItem::class)->save($admin, ['sku' => 'FINAL', 'name' => 'Busi', 'purchase_price' => '1', 'selling_price' => '2', 'minimum_stock' => 0, 'unit' => 'pcs', 'is_active' => true]);
    app(StockLedger::class)->move($admin, $item, 4, 'in', 'purchase');
    $part = app(UseServicePart::class)->use($admin, $order, $item->id, 1);
    DB::table('receipts')->insert(['public_id' => (string) Str::uuid(), 'receipt_number' => 'BON-LOCK', 'service_order_id' => $order->id, 'cashier_id' => $admin->id, 'transaction_date' => now(), 'status' => 'final']);
    expect(fn () => app(UseServicePart::class)->use($admin, $order, $item->id, 1))->toThrow(ValidationException::class);
    expect(fn () => app(UseServicePart::class)->returnPart($admin, $order, $part))->toThrow(ValidationException::class);
    expect(fn () => app(UseServicePart::class)->returnAll($admin, $order))->toThrow(ValidationException::class);
    expect($item->fresh()->current_stock)->toBe(3)->and($part->fresh()->returned_at)->toBeNull();
    DB::table('receipts')->update(['status' => 'voided', 'voided_at' => now()]);
    app(UseServicePart::class)->returnAll($admin, $order);
    expect($item->fresh()->current_stock)->toBe(4);
});

it('prevents rewriting service snapshots or deleting used parts', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $item = app(SaveInventoryItem::class)->save($admin, ['sku' => 'IMM', 'name' => 'Busi', 'purchase_price' => '1', 'selling_price' => '2', 'minimum_stock' => 0, 'unit' => 'pcs', 'is_active' => true]);
    app(StockLedger::class)->move($admin, $item, 4, 'in', 'purchase');
    $part = app(UseServicePart::class)->use($admin, $order, $item->id, 1);
    expect(fn () => $part->update(['quantity' => 2]))->toThrow(LogicException::class);
    expect(fn () => $part->delete())->toThrow(LogicException::class);
});

it('returns one full part once including archived inventory preserving transaction snapshots', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $item = app(SaveInventoryItem::class)->save($admin, ['sku' => 'RET', 'name' => 'Busi', 'purchase_price' => '1', 'selling_price' => '2.50', 'minimum_stock' => 0, 'unit' => 'pcs', 'is_active' => true]);
    app(StockLedger::class)->move($admin, $item, 4, 'in', 'purchase');
    $part = app(UseServicePart::class)->use($admin, $order, $item->id, 2);
    $item->delete();
    $returned = app(UseServicePart::class)->returnPart($admin, $order, $part);
    app(UseServicePart::class)->returnPart($admin, $order, $part);
    expect($returned->returned_at)->not->toBeNull()
        ->and(InventoryItem::withTrashed()->find($item->id)->current_stock)->toBe(4)
        ->and(StockMovement::where('type', 'return')->count())->toBe(1)
        ->and($returned->subtotal)->toBe('5.00');
    expect(StockMovement::where('type', 'return')->sole()->reason)->toBe('return');
});

it('returnAll reverses active usage once for cancellation using service_cancelled reason', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $item = app(SaveInventoryItem::class)->save($admin, ['sku' => 'CAN', 'name' => 'Busi', 'purchase_price' => '1', 'selling_price' => '2', 'minimum_stock' => 0, 'unit' => 'pcs', 'is_active' => true]);
    app(StockLedger::class)->move($admin, $item, 4, 'in', 'purchase');
    app(UseServicePart::class)->use($admin, $order, $item->id, 1);
    app(UseServicePart::class)->use($admin, $order, $item->id, 2);
    $order->update(['status' => 'cancelled']);
    app(UseServicePart::class)->returnAll($admin, $order);
    app(UseServicePart::class)->returnAll($admin, $order);
    expect($item->fresh()->current_stock)->toBe(4)
        ->and(ServiceItem::whereNotNull('returned_at')->count())->toBe(2)
        ->and(StockMovement::where('reason', 'service_cancelled')->count())->toBe(2);
});

it('assigned mechanic uses stock with server price and immutable description snapshot', function () {
    $actor = User::factory()->create(['role' => 'mechanic']);
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $actor->id]);
    $item = app(SaveInventoryItem::class)->save($admin, ['sku' => 'SP-1', 'name' => 'Busi', 'purchase_price' => '12000', 'selling_price' => '15000.25', 'minimum_stock' => 1, 'unit' => 'pcs', 'is_active' => true]);
    app(StockLedger::class)->move($admin, $item, 10, 'in', 'purchase');
    $part = app(UseServicePart::class)->use($actor, $order, $item->id, 2);
    expect($item->fresh()->current_stock)->toBe(8)
        ->and($part->description)->toBe('Busi')
        ->and($part->unit_price)->toBe('15000.25')
        ->and($part->subtotal)->toBe('30000.50')
        ->and($part->used_by)->toBe($actor->id);
    $item->update(['name' => 'Busi baru', 'selling_price' => '99999.00']);
    expect($part->fresh()->description)->toBe('Busi')->and($part->fresh()->unit_price)->toBe('15000.25');
    $movement = StockMovement::where('reason', 'service')->sole();
    expect($movement->quantity)->toBe(-2)->and($movement->reference_id)->toBe($part->id);
});
