<?php

namespace App\Livewire;

use App\Actions\StockLedger;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class InventoryStock extends Component
{
    public bool $showForm = false;

    #[Locked]
    public ?int $stockItemId = null;

    /** @var array<string,mixed> */
    public array $stockForm = [];

    public function boot(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
    }

    public function mount(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
    }

    #[On('open-inventory-stock')]
    public function openStock(int $id, string $type): void
    {
        $item = InventoryItem::query()->findOrFail($id);
        $this->authorize('update', $item);
        abort_unless(in_array($type, ['in', 'adjustment'], true), 422);
        $this->showForm = true;
        $this->stockItemId = $id;
        $this->stockForm = ['type' => $type, 'quantity' => '', 'reason' => ''];
        $this->resetValidation();
    }

    public function closeStock(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
        $this->reset('stockItemId', 'stockForm', 'showForm');
        $this->resetValidation();
        $this->dispatch('inventory-stock-closed');
    }

    public function saveStock(): void
    {
        $this->resetValidation();
        abort_if($this->stockItemId === null, 404);
        $item = InventoryItem::query()->findOrFail($this->stockItemId);
        $this->authorize('update', $item);
        $data = $this->validate(['stockForm.type' => ['required', Rule::in(['in', 'adjustment'])], 'stockForm.quantity' => ['required', 'integer', 'between:'.(-StockLedger::MAX_STOCK).','.StockLedger::MAX_STOCK, 'not_in:0'], 'stockForm.reason' => ['required', 'string', 'max:255']])['stockForm'];
        try {
            app(StockLedger::class)->move($this->actor(), $item, (int) $data['quantity'], $data['type'], $data['reason']);
        } catch (ValidationException $e) {
            $this->errorsFor($e, 'stockForm');

            return;
        }
        $this->closeStock();
        $this->dispatch('inventory-stock-saved')->to(Inventory::class);
    }

    public function updatedShowForm(bool $open): void
    {
        if (! $open) {
            $this->closeStock();
        }
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
        return view('livewire.inventory-stock', ['stockItem' => ! $this->showForm || $this->stockItemId === null ? null : InventoryItem::query()->findOrFail($this->stockItemId)]);
    }
}
