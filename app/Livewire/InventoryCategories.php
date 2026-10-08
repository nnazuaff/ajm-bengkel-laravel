<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class InventoryCategories extends Component
{
    public bool $showForm = false;

    public string $categoryName = '';

    public function boot(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
    }

    public function mount(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
    }

    #[On('open-inventory-categories')]
    public function openForm(): void
    {
        $this->authorize('create', InventoryItem::class);
        $this->reset('categoryName');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
        $this->reset('categoryName', 'showForm');
        $this->resetValidation();
        $this->dispatch('inventory-categories-closed');
    }

    public function updatedShowForm(bool $open): void
    {
        if (! $open) {
            $this->closeForm();
        }
    }

    public function saveCategory(): void
    {
        $this->authorize('create', InventoryItem::class);
        $this->showForm = true;
        $this->resetValidation();
        $this->categoryName = trim($this->categoryName);
        $data = $this->validate(['categoryName' => ['required', 'string', 'max:120', 'unique:inventory_categories,name']]);
        DB::transaction(function () use ($data): void {
            $category = InventoryCategory::create(['name' => $data['categoryName']]);
            $audit = AuditLog::create(['actor_id' => $this->actor()->id, 'action' => 'inventory_category.created', 'entity_type' => InventoryCategory::class, 'entity_id' => $category->id, 'context' => ['name' => $category->name]]);
            if (! $audit->exists) {
                throw new \RuntimeException('Audit gagal disimpan.');
            }
        });
        $this->categoryName = '';
        $this->closeForm();
        $this->dispatch('inventory-category-saved')->to(Inventory::class);
        $this->dispatch('inventory-category-saved')->to(InventoryEditor::class);
    }

    public function deleteCategory(int $id): void
    {
        $this->authorize('create', InventoryItem::class);
        $this->showForm = true;
        $this->resetValidation('categoryDeletion');
        try {
            DB::transaction(function () use ($id): void {
                $category = InventoryCategory::query()->lockForUpdate()->findOrFail($id);
                if ($category->items()->withTrashed()->exists()) {
                    throw ValidationException::withMessages(['categoryDeletion' => 'Kategori masih dipakai barang, termasuk arsip. Pindahkan kategori barang terlebih dahulu.']);
                }
                if (! $category->delete()) {
                    throw new \RuntimeException('Kategori gagal dihapus.');
                }
                $audit = AuditLog::create(['actor_id' => $this->actor()->id, 'action' => 'inventory_category.deleted', 'entity_type' => InventoryCategory::class, 'entity_id' => $id, 'context' => ['name' => $category->name]]);
                if (! $audit->exists) {
                    throw new \RuntimeException('Audit gagal disimpan.');
                }
            }, attempts: 5);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1451) {
                throw $exception;
            }
            throw ValidationException::withMessages(['categoryDeletion' => 'Kategori sedang dipakai barang. Muat ulang daftar.']);
        }
        $this->dispatch('inventory-category-deleted', id: $id)->to(Inventory::class);
        $this->dispatch('inventory-category-deleted', id: $id)->to(InventoryEditor::class);
        session()->flash('inventoryCategoryStatus', 'Kategori berhasil dihapus.');
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    public function render(): View
    {
        return view('livewire.inventory-categories', ['categories' => $this->showForm ? InventoryCategory::query()->orderBy('name')->get() : collect()]);
    }
}
