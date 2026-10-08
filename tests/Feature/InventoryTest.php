<?php

use App\Actions\SaveInventoryItem;
use App\Actions\StockLedger;
use App\Livewire\Inventory;
use App\Livewire\InventoryCategories;
use App\Livewire\InventoryEditor;
use App\Livewire\InventoryHistory;
use App\Livewire\InventoryStock;
use App\Models\AuditLog;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('provides searchable inventory creation restock adjustment history and category controls', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    $page = Livewire::test(Inventory::class)->assertSee('Inventori');
    Livewire::test(InventoryEditor::class)->call('create')
        ->set('form', ['sku' => 'UI-1', 'name' => 'Oli UI', 'purchase_price' => '35000.00', 'selling_price' => '45000.00', 'minimum_stock' => 2, 'unit' => 'botol', 'is_active' => true])
        ->call('save')->assertHasNoErrors();
    $page->dispatch('inventory-saved')->assertSee('Oli UI');
    $item = InventoryItem::where('sku', 'UI-1')->sole();
    $stock = Livewire::test(InventoryStock::class)->call('openStock', $item->id, 'in')->set('stockForm.quantity', '5')->set('stockForm.reason', 'Pembelian pemasok')
        ->call('saveStock')->assertHasNoErrors();
    $stock->call('openStock', $item->id, 'adjustment')->set('stockForm.quantity', '-1')->set('stockForm.reason', 'Koreksi hitung fisik')
        ->call('saveStock')->assertHasNoErrors();
    Livewire::test(InventoryHistory::class)->call('history', $item->id)->assertSee('Koreksi hitung fisik')->call('closeHistory');
    expect($item->fresh()->current_stock)->toBe(4);
    Livewire::test(InventoryCategories::class)->call('openForm')->set('categoryName', 'Pelumas')->call('saveCategory')->assertHasNoErrors();
    $page->dispatch('inventory-category-saved')->assertSee('Pelumas')->set('search', 'Unmatched')->assertDontSee('Oli UI');
});

it('deletes an unused category with audit and clears selected form and filter', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $category = InventoryCategory::factory()->create(['name' => 'Kategori kosong']);
    $page = Livewire::actingAs($actor)->test(Inventory::class)->set('categoryFilter', (string) $category->id);
    $editor = Livewire::test(InventoryEditor::class)->call('create')->set('form.category_id', (string) $category->id);
    Livewire::test(InventoryCategories::class)->call('openForm')->call('deleteCategory', $category->id)->assertHasNoErrors();
    $page->dispatch('inventory-category-deleted', id: $category->id)->assertSet('categoryFilter', '')->assertSee('Kategori berhasil dihapus.');
    $editor->dispatch('inventory-category-deleted', id: $category->id)->assertSet('form.category_id', '');
    expect(InventoryCategory::find($category->id))->toBeNull();
    expect(AuditLog::where('action', 'inventory_category.deleted')->sole()->context)->toBe(['name' => 'Kategori kosong']);
});

it('rejects deleting categories used by active or archived inventory', function (bool $archived) {
    $category = InventoryCategory::factory()->create();
    $item = InventoryItem::factory()->create(['category_id' => $category->id]);
    if ($archived) {
        $item->delete();
    }
    Livewire::actingAs(User::factory()->create(['role' => 'owner']))->test(InventoryCategories::class)->call('openForm')
        ->call('deleteCategory', $category->id)->assertHasErrors('categoryDeletion');
    expect(InventoryCategory::find($category->id))->not->toBeNull();
    expect($item->fresh()->category_id)->toBe($category->id);
})->with([false, true]);

it('rolls category deletion back when audit persistence fails', function () {
    $category = InventoryCategory::factory()->create();
    $component = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(InventoryCategories::class)->call('openForm');
    AuditLog::creating(fn () => false);
    try {
        expect(fn () => $component->call('deleteCategory', $category->id))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(InventoryCategory::find($category->id))->not->toBeNull();
});

it('filters low out and categories while preserving archived history', function () {
    $this->actingAs(User::factory()->create(['role' => 'owner']));
    $category = InventoryCategory::factory()->create(['name' => 'Pelumas']);
    $low = InventoryItem::factory()->create(['name' => 'Stok rendah', 'current_stock' => 1, 'minimum_stock' => 2, 'category_id' => $category->id]);
    InventoryItem::factory()->create(['name' => 'Barang kosong', 'current_stock' => 0]);
    InventoryItem::factory()->create(['name' => 'Stok cukup', 'current_stock' => 10]);
    $page = Livewire::test(Inventory::class)->set('stockFilter', 'out')->assertSee('Barang kosong')->assertDontSee('Stok cukup')
        ->set('stockFilter', 'low')->assertSee('Stok rendah')->assertDontSee('Stok cukup')
        ->set('categoryFilter', (string) $category->id)->assertSee('Stok rendah')->assertDontSee('Barang kosong');
    Livewire::test(InventoryEditor::class)->call('edit', $low->id)->set('form.is_active', false)->call('save')->assertHasNoErrors();
    expect($low->fresh()->is_active)->toBeFalse();
    $page->call('archive', $low->id);
    Livewire::test(InventoryHistory::class)->call('history', $low->id)->assertSee('Riwayat');
    expect($low->fresh()->trashed())->toBeTrue()->and(InventoryItem::withTrashed()->find($low->id)->current_stock)->toBe(1);
});

it('displays validation errors for missing master fields and invalid adjustments', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $page = Livewire::test(InventoryEditor::class)->call('create')->call('save')->assertHasErrors(['form.name', 'form.sku']);
    $item = InventoryItem::factory()->create();
    $page->call('closeForm');
    $page = Livewire::test(InventoryStock::class)->call('openStock', $item->id, 'in')->set('stockForm.quantity', '-1')->set('stockForm.reason', 'purchase')->call('saveStock')->assertHasErrors('stockForm.quantity');
    $page->set('stockForm.quantity', '1.5')->call('saveStock')->assertHasErrors('stockForm.quantity');
    $page->set('stockForm.quantity', '1')->set('stockForm.reason', '')->call('saveStock')->assertHasErrors('stockForm.reason');
});

it('rejects invalid monetary input and duplicate sku', function (string $price) {
    $actor = User::factory()->create(['role' => 'admin']);
    $input = ['sku' => 'MONEY', 'name' => 'Oli', 'purchase_price' => '1', 'selling_price' => $price, 'minimum_stock' => 0, 'unit' => 'pcs', 'is_active' => true];
    expect(fn () => app(SaveInventoryItem::class)->save($actor, $input))->toThrow(ValidationException::class);
})->with(['-1', '1.001', '1000000000000', '1e5', 'NaN']);

it('rejects inventory access for non managers', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));
    Livewire::test(Inventory::class)->assertForbidden();
})->with(['mechanic', 'customer']);

it('forbids submitted stock in the master action and audits price changes separately', function () {
    $actor = User::factory()->create(['role' => 'owner']);
    $data = ['sku' => 'PRICE', 'name' => 'Oli', 'purchase_price' => '1.00', 'selling_price' => '2.00', 'minimum_stock' => 0, 'unit' => 'pcs', 'is_active' => true];
    expect(fn () => app(SaveInventoryItem::class)->save($actor, [...$data, 'current_stock' => 25]))->toThrow(ValidationException::class);
    $item = app(SaveInventoryItem::class)->save($actor, $data);
    app(SaveInventoryItem::class)->save($actor, [...$data, 'selling_price' => '3.50'], $item);
    expect(AuditLog::where('action', 'inventory.price_changed')->count())->toBe(1)->and($item->fresh()->current_stock)->toBe(0);
});

it('validates signed movement limits without changing stock', function (int $delta, string $type, string $reason) {
    $actor = User::factory()->create(['role' => 'admin']);
    $item = InventoryItem::factory()->create(['current_stock' => 5]);
    expect(fn () => app(StockLedger::class)->move($actor, $item, $delta, $type, $reason))->toThrow(ValidationException::class);
    expect($item->fresh()->current_stock)->toBe(5)->and(StockMovement::count())->toBe(0);
})->with([[0, 'in', 'purchase'], [-6, 'out', 'service'], [-1, 'in', 'purchase'], [1, 'out', 'service'], [1, 'bogus', 'reason'], [1, 'in', '   '], [2147483647, 'in', 'purchase'], [-2147483648, 'adjustment', 'manual_adjustment']]);

it('rolls back when an item observer refuses the stock update', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $item = InventoryItem::factory()->create(['current_stock' => 5]);
    InventoryItem::updating(fn () => false);
    try {
        expect(fn () => app(StockLedger::class)->move($actor, $item, 1, 'in', 'purchase'))->toThrow(RuntimeException::class);
    } finally {
        InventoryItem::flushEventListeners();
    }
    expect($item->fresh()->current_stock)->toBe(5)->and(StockMovement::count())->toBe(0);
});

it('rolls back when a movement observer refuses insertion', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $item = InventoryItem::factory()->create(['current_stock' => 5]);
    StockMovement::creating(fn () => false);
    try {
        expect(fn () => app(StockLedger::class)->move($actor, $item, 1, 'in', 'purchase'))->toThrow(RuntimeException::class);
    } finally {
        StockMovement::flushEventListeners();
        StockMovement::clearBootedModels();
    }
    expect($item->fresh()->current_stock)->toBe(5)->and(StockMovement::count())->toBe(0);
});

it('rolls stock back when an audit observer silently vetoes insertion', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $item = InventoryItem::factory()->create(['current_stock' => 5]);
    AuditLog::creating(fn () => false);
    try {
        expect(fn () => app(StockLedger::class)->move($actor, $item, 1, 'in', 'purchase'))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($item->fresh()->current_stock)->toBe(5)->and(StockMovement::count())->toBe(0);
});

it('rolls stock and movement back when auditing fails', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $item = InventoryItem::factory()->create(['current_stock' => 5]);
    AuditLog::creating(fn () => throw new RuntimeException('audit unavailable'));
    try {
        expect(fn () => app(StockLedger::class)->move($actor, $item, 1, 'in', 'purchase'))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($item->fresh()->current_stock)->toBe(5)->and(StockMovement::count())->toBe(0);
});

it('non managers cannot save inventory masters', function (string $role) {
    $actor = User::factory()->create(['role' => $role]);
    expect(fn () => app(SaveInventoryItem::class)->save($actor, []))->toThrow(AuthorizationException::class);
})->with(['mechanic', 'customer']);

it('rejects rewriting or deleting ledger records', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $item = app(SaveInventoryItem::class)->save($actor, ['sku' => 'IMM', 'name' => 'Oli', 'purchase_price' => '1', 'selling_price' => '2', 'minimum_stock' => 0, 'unit' => 'pcs', 'is_active' => true]);
    $movement = app(StockLedger::class)->move($actor, $item, 1, 'in', 'purchase');
    expect(fn () => $movement->update(['reason' => 'rewrite']))->toThrow(LogicException::class);
    expect(fn () => $movement->delete())->toThrow(LogicException::class);
    expect($movement->fresh()->reason)->toBe('purchase');
});

it('creates an inventory master at zero stock and restocks through the atomic ledger', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $item = app(SaveInventoryItem::class)->save($actor, [
        'sku' => 'OLI-001', 'name' => 'Oli mesin', 'purchase_price' => '35000.00',
        'selling_price' => '45000.50', 'minimum_stock' => 2, 'unit' => 'botol', 'is_active' => true,
    ]);
    expect($item->current_stock)->toBe(0);
    $movement = app(StockLedger::class)->move($actor, $item, 10, 'in', 'purchase');
    expect($item->fresh()->current_stock)->toBe(10)
        ->and($movement->quantity)->toBe(10)
        ->and($movement->stock_before)->toBe(0)
        ->and($movement->stock_after)->toBe(10)
        ->and(StockMovement::count())->toBe(1)
        ->and(AuditLog::where('action', 'inventory.stock_changed')->count())->toBe(1);
});
