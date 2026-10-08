<?php

namespace App\Livewire;

use App\Models\InventoryItem;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryHistory extends Component
{
    use WithPagination;

    public bool $showForm = false;

    #[Locked]
    public ?int $historyId = null;

    public function boot(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
    }

    public function mount(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
    }

    #[On('open-inventory-history')]
    public function history(int $id): void
    {
        $item = InventoryItem::withTrashed()->findOrFail($id);
        $this->authorize('view', $item);
        $this->resetPage('movementPage');
        $this->resetValidation();
        $this->showForm = true;
        $this->historyId = $id;
    }

    public function closeHistory(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
        $this->reset('historyId', 'showForm');
        $this->resetPage('movementPage');
        $this->resetValidation();
        $this->dispatch('inventory-history-closed');
    }

    public function updatedShowForm(bool $open): void
    {
        if (! $open) {
            $this->closeHistory();
        }
    }

    public function render(): View
    {
        $item = ! $this->showForm || $this->historyId === null ? null : InventoryItem::withTrashed()->findOrFail($this->historyId);
        if ($item !== null) {
            $this->authorize('view', $item);
        }

        return view('livewire.inventory-history', ['historyItem' => $item, 'movements' => $item?->movements()->with('creator')->orderByDesc('id')->paginate(15, pageName: 'movementPage')]);
    }
}
