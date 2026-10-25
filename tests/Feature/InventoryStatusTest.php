<?php

use App\Livewire\Inventory;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Livewire\Livewire;

it('sets inventory availability from the list without changing stock and audits only changes', function () {
    $item = InventoryItem::factory()->create(['is_active' => true, 'current_stock' => 7]);
    $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(Inventory::class)
        ->assertSeeHtml('role="switch"')->call('setActive', $item->id, false)->assertHasNoErrors();
    expect($item->fresh()->is_active)->toBeFalse()->and($item->fresh()->current_stock)->toBe(7);
    $page->call('setActive', $item->id, false);
    expect(AuditLog::where('action', 'inventory.status_changed')->count())->toBe(1);
    $page->call('setActive', $item->id, true);
    expect($item->fresh()->is_active)->toBeTrue()->and(StockMovement::count())->toBe(0);
    expect(AuditLog::where('action', 'inventory.status_changed')->latest('id')->first()->context)
        ->toBe(['before' => false, 'after' => true]);
});

it('rolls availability back when its audit fails', function () {
    $item = InventoryItem::factory()->create(['is_active' => true]);
    $page = Livewire::actingAs(User::factory()->create(['role' => 'owner']))->test(Inventory::class);
    AuditLog::creating(fn () => false);
    try {
        expect(fn () => $page->call('setActive', $item->id, false))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($item->fresh()->is_active)->toBeTrue();
});

it('rejects availability changes after staff permission is revoked', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $item = InventoryItem::factory()->create(['is_active' => true]);
    $page = Livewire::actingAs($actor)->test(Inventory::class);
    $actor->forceFill(['role' => 'mechanic'])->save();
    $page->call('setActive', $item->id, false)->assertForbidden();
    expect($item->fresh()->is_active)->toBeTrue();
});

it('does not reactivate archived inventory', function () {
    $item = InventoryItem::factory()->create(['is_active' => false]);
    $item->delete();
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(Inventory::class)
        ->call('setActive', $item->id, true)->assertNotFound();
    expect($item->fresh()->is_active)->toBeFalse();
});
