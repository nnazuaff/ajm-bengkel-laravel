<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use App\Support\WorkshopInput;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Inventory extends Component
{
    use WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public string $stockFilter = '';

    public function boot(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
    }

    public function mount(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStockFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', InventoryItem::class);
        $this->dispatch('open-inventory-create')->to(InventoryEditor::class);
    }

    public function edit(int $id): void
    {
        $this->authorize('update', InventoryItem::query()->findOrFail($id));
        $this->dispatch('open-inventory-edit', id: $id)->to(InventoryEditor::class);
    }

    public function openStock(int $id, string $type): void
    {
        $this->authorize('update', InventoryItem::query()->findOrFail($id));
        abort_unless(in_array($type, ['in', 'adjustment'], true), 422);
        $this->dispatch('open-inventory-stock', id: $id, type: $type)->to(InventoryStock::class);
    }

    public function openCategories(): void
    {
        $this->authorize('create', InventoryItem::class);
        $this->dispatch('open-inventory-categories')->to(InventoryCategories::class);
    }

    public function history(int $id): void
    {
        $this->authorize('view', InventoryItem::withTrashed()->findOrFail($id));
        $this->dispatch('open-inventory-history', id: $id)->to(InventoryHistory::class);
    }

    #[On('inventory-saved')]
    public function inventorySaved(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
        $this->resetPage();
        session()->flash('inventoryStatus', 'Barang berhasil disimpan. Stok hanya berubah melalui mutasi.');
    }

    #[On('inventory-stock-saved')]
    public function stockSaved(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
        session()->flash('inventoryStatus', 'Mutasi stok tercatat.');
    }

    #[On('inventory-category-saved')]
    public function categorySaved(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
        session()->flash('inventoryStatus', 'Kategori berhasil ditambahkan.');
    }

    #[On('inventory-category-deleted')]
    public function categoryDeleted(int $id): void
    {
        $this->authorize('viewAny', InventoryItem::class);
        if (InventoryCategory::query()->whereKey($id)->exists()) {
            return;
        }
        if ($this->categoryFilter === (string) $id) {
            $this->categoryFilter = '';
        }
        $this->resetPage();
        session()->flash('inventoryStatus', 'Kategori berhasil dihapus.');
    }

    public function archive(int $id): void
    {
        $item = InventoryItem::query()->findOrFail($id);
        $this->authorize('delete', $item);
        DB::transaction(function () use ($item): void {
            $current = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $current->is_active = false;
            $current->save();
            $current->delete();
            $audit = AuditLog::create(['actor_id' => $this->actor()->id, 'action' => 'inventory.archived', 'entity_type' => InventoryItem::class, 'entity_id' => $current->id, 'context' => ['stock' => $current->current_stock]]);
            if (! $audit->exists) {
                throw new \RuntimeException('Audit gagal disimpan.');
            }
        });
        session()->flash('inventoryStatus', 'Barang diarsipkan. Riwayat dan pengembalian tetap tersedia.');
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    public function formatMoney(string $decimal): string
    {
        if (! is_numeric($decimal) || ! preg_match('/\A[0-9]+(?:\.[0-9]+)?\z/', $decimal)) {
            throw new \InvalidArgumentException('Nilai harus desimal non-negatif.');
        }

        return 'Rp '.str_replace('.', ',', bcadd($decimal, '0', 2));
    }

    public function render(): View
    {
        $search = Str::lower(Str::squish(Str::substr($this->search, 0, 120)));
        $items = InventoryItem::query()->with('category')->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [WorkshopInput::like($search)])->orWhereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [WorkshopInput::like($search)])))
            ->when($this->categoryFilter !== '', fn (Builder $q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->stockFilter === 'low', fn (Builder $q) => $q->whereColumn('current_stock', '<=', 'minimum_stock'))
            ->when($this->stockFilter === 'out', fn (Builder $q) => $q->where('current_stock', 0))->orderBy('name')->paginate(15);

        return view('livewire.inventory', ['items' => $items, 'categories' => InventoryCategory::query()->orderBy('name')->get()]);
    }
}
