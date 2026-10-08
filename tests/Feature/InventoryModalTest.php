<?php

use App\Livewire\Inventory;
use App\Livewire\InventoryCategories;
use App\Livewire\InventoryEditor;
use App\Livewire\InventoryHistory;
use App\Livewire\InventoryStock;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('keeps inventory forms out of the initial list and targets modal children', function () {
    $item = InventoryItem::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(Inventory::class)
        ->assertSee('Kelola kategori')->assertDontSeeHtml('wire:model="form.sku"')
        ->assertDontSeeHtml('wire:model="stockForm.quantity"')->assertDontSeeHtml('wire:model="categoryName"')
        ->call('create')->assertDispatchedTo(InventoryEditor::class, 'open-inventory-create')
        ->call('edit', $item->id)->assertDispatchedTo(InventoryEditor::class, 'open-inventory-edit')
        ->call('openStock', $item->id, 'in')->assertDispatchedTo(InventoryStock::class, 'open-inventory-stock')
        ->call('openCategories')->assertDispatchedTo(InventoryCategories::class, 'open-inventory-categories')
        ->call('history', $item->id)->assertDispatchedTo(InventoryHistory::class, 'open-inventory-history');
});

it('keeps invalid inventory drafts open and clears cancel escape and reopen state', function () {
    $editor = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(InventoryEditor::class)
        ->call('create')->call('save')->assertHasErrors('form.name')->assertSet('showForm', true)
        ->set('showForm', false)->assertSet('editingId', null)->assertSet('form', [])->assertHasNoErrors()
        ->assertDispatched('inventory-editor-closed')->call('create')->assertSet('form.sku', '')->assertHasNoErrors();
    $item = InventoryItem::factory()->create();
    $editor->call('edit', $item->id)->call('closeForm')->call('create')->assertSet('editingId', null);
    Livewire::test(InventoryStock::class)->call('openStock', $item->id, 'adjustment')
        ->set('stockForm.quantity', '-999')->set('stockForm.reason', 'Koreksi')->call('saveStock')
        ->assertHasErrors('stockForm.quantity')->assertSet('showForm', true)
        ->set('showForm', false)->assertSet('stockItemId', null)->assertSet('stockForm', [])->assertHasNoErrors()
        ->call('openStock', $item->id, 'in')->assertSet('stockForm.quantity', '')->assertHasNoErrors();
    Livewire::test(InventoryCategories::class)->call('openForm')->call('saveCategory')
        ->assertHasErrors('categoryName')->assertSet('showForm', true)
        ->set('showForm', false)->assertSet('categoryName', '')->assertHasNoErrors()
        ->call('openForm')->assertHasNoErrors();
});

it('closes saved inventory modals and refreshes the list without trusting category events', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $item = InventoryItem::factory()->create();
    $category = InventoryCategory::factory()->create();
    $page = Livewire::actingAs($actor)->test(Inventory::class)->set('categoryFilter', (string) $category->id);
    $editor = Livewire::test(InventoryEditor::class)->call('edit', $item->id)->set('form.category_id', (string) $category->id);
    $page->dispatch('inventory-category-deleted', id: $category->id)->assertSet('categoryFilter', (string) $category->id);
    $editor->dispatch('inventory-category-deleted', id: $category->id)->assertSet('form.category_id', (string) $category->id);
    Livewire::test(InventoryCategories::class)->call('openForm')->call('deleteCategory', $category->id)
        ->assertDispatched('inventory-category-deleted')->assertSet('showForm', true);
    $page->dispatch('inventory-category-deleted', id: $category->id)->assertSet('categoryFilter', '');
    $editor->dispatch('inventory-category-deleted', id: $category->id)->assertSet('form.category_id', '')
        ->set('form.name', 'Nama baru')->call('save')->assertHasNoErrors()->assertSet('showForm', false)
        ->assertDispatchedTo(Inventory::class, 'inventory-saved');
    $page->dispatch('inventory-saved')->assertSee('Nama baru');
    Livewire::test(InventoryStock::class)->call('openStock', $item->id, 'in')
        ->set('stockForm.quantity', '2')->set('stockForm.reason', 'Pembelian')->call('saveStock')
        ->assertSet('showForm', false)->assertSet('stockItemId', null)->assertDispatchedTo(Inventory::class, 'inventory-stock-saved');
    $page->dispatch('inventory-stock-saved')->assertSee('Mutasi stok tercatat.');
});

it('locks inventory identifiers against client tampering', function (string $component, string $property) {
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test($component)->set($property, 123);
})->with([[InventoryEditor::class, 'editingId'], [InventoryStock::class, 'stockItemId'], [InventoryHistory::class, 'historyId']])->throws(CannotUpdateLockedPropertyException::class);

it('protects all inventory modal mounts and subsequent requests after role changes', function (string $component, string $method) {
    foreach (['mechanic', 'customer'] as $role) {
        Livewire::actingAs(User::factory()->create(['role' => $role]))->test($component)->assertForbidden();
    }
    $actor = User::factory()->create(['role' => 'admin']);
    $modal = Livewire::actingAs($actor)->test($component);
    $actor->forceFill(['role' => 'mechanic'])->save();
    $modal->call($method)->assertForbidden();
})->with([[InventoryEditor::class, 'create'], [InventoryStock::class, 'closeStock'], [InventoryCategories::class, 'openForm'], [InventoryHistory::class, 'closeHistory']]);

it('rejects a category deleted before an inventory draft is saved', function () {
    $category = InventoryCategory::factory()->create();
    $item = InventoryItem::factory()->create();
    $editor = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(InventoryEditor::class)
        ->call('edit', $item->id)->set('form.category_id', (string) $category->id);
    $category->delete();
    $editor->call('save')->assertHasErrors('form.category_id')->assertSet('showForm', true);
    expect($item->fresh()->category_id)->toBeNull();
});

it('resets stock history pagination on close and switching items', function () {
    $first = InventoryItem::factory()->create();
    $second = InventoryItem::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => 'owner']))->test(InventoryHistory::class)
        ->call('history', $first->id)->set('paginators.movementPage', 3)
        ->call('history', $second->id)->assertSet('paginators.movementPage', 1)
        ->set('showForm', false)->assertSet('historyId', null)->assertSet('paginators.movementPage', 1)
        ->assertDispatched('inventory-history-closed');
});

it('maps a racing category foreign key failure into a modal validation error', function () {
    $category = InventoryCategory::factory()->create();
    $modal = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(InventoryCategories::class)->call('openForm');
    $pdo = new PDOException('Foreign key constraint');
    $pdo->errorInfo = ['23000', 1451, 'Foreign key constraint'];
    InventoryCategory::deleting(fn () => throw new QueryException('mysql', 'delete from inventory_categories', [], $pdo));
    try {
        $modal->call('deleteCategory', $category->id)->assertHasErrors('categoryDeletion')->assertSet('showForm', true);
    } finally {
        InventoryCategory::flushEventListeners();
    }
    expect(InventoryCategory::find($category->id))->not->toBeNull();
});

it('keeps duplicate category errors open and closes a successfully saved category', function () {
    $category = InventoryCategory::factory()->create(['name' => 'Pelumas']);
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(InventoryCategories::class)
        ->call('openForm')->set('categoryName', $category->name)->call('saveCategory')
        ->assertHasErrors('categoryName')->assertSet('showForm', true)
        ->set('categoryName', '  Ban  ')->call('saveCategory')->assertHasNoErrors()
        ->assertSet('showForm', false)->assertSet('categoryName', '')
        ->assertDispatched('inventory-categories-closed')->assertDispatchedTo(Inventory::class, 'inventory-category-saved');
    expect(InventoryCategory::where('name', 'Ban')->count())->toBe(1);
});
