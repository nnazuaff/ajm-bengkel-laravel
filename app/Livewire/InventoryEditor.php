<?php

namespace App\Livewire;

use App\Actions\SaveInventoryItem;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class InventoryEditor extends Component
{
    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string,mixed> */
    public array $form = [];

    public function boot(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
    }

    public function mount(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
    }

    #[On('open-inventory-create')]
    public function create(): void
    {
        $this->authorize('create', InventoryItem::class);
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['sku' => '', 'name' => '', 'category_id' => '', 'brand' => '', 'purchase_price' => '0.00', 'selling_price' => '0.00', 'minimum_stock' => 0, 'unit' => 'pcs', 'supplier' => '', 'storage_location' => '', 'is_active' => true];
        $this->showForm = true;
    }

    #[On('open-inventory-edit')]
    public function edit(int $id): void
    {
        $item = InventoryItem::query()->findOrFail($id);
        $this->authorize('update', $item);
        $this->resetValidation();
        $this->editingId = $id;
        $this->form = $item->only(['sku', 'name', 'category_id', 'brand', 'purchase_price', 'selling_price', 'minimum_stock', 'unit', 'supplier', 'storage_location', 'is_active']);
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
        $this->reset('editingId', 'form', 'showForm');
        $this->resetValidation();
        $this->dispatch('inventory-editor-closed');
    }

    public function save(): void
    {
        $item = $this->editingId === null ? null : InventoryItem::query()->findOrFail($this->editingId);
        $this->authorize($item === null ? 'create' : 'update', $item ?? InventoryItem::class);
        $this->resetValidation();
        $input = $this->form;
        foreach (['category_id', 'brand', 'supplier', 'storage_location'] as $field) {
            if (($input[$field] ?? null) === '') {
                $input[$field] = null;
            }
        }
        try {
            app(SaveInventoryItem::class)->save($this->actor(), $input, $item);
        } catch (ValidationException $e) {
            $this->errorsFor($e, 'form');

            return;
        }
        $this->closeForm();
        $this->dispatch('inventory-saved')->to(Inventory::class);
    }

    public function updatedShowForm(bool $open): void
    {
        if (! $open) {
            $this->closeForm();
        }
    }

    #[On('inventory-category-deleted')]
    public function categoryDeleted(int $id): void
    {
        $this->authorize('viewAny', InventoryItem::class);
        if ((string) ($this->form['category_id'] ?? '') === (string) $id && ! InventoryCategory::query()->whereKey($id)->exists()) {
            $this->form['category_id'] = '';
            $this->resetValidation('form.category_id');
        }
    }

    #[On('inventory-category-saved')]
    public function refreshCategories(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function errorsFor(ValidationException $e, string $prefix): void
    {
        foreach ($e->errors() as $key => $messages) {
            foreach ($messages as $message) {
                $this->addError($prefix.'.'.$key, $message);
            }
        }
    }

    public function render(): View
    {
        return view('livewire.inventory-editor', ['categories' => $this->showForm ? InventoryCategory::query()->orderBy('name')->get() : collect()]);
    }
}
