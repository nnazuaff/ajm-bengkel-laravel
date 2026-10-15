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

    #[Locked]
    public ?int $openingStock = null;

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
        $this->openingStock = $type === 'adjustment' ? $item->current_stock : null;
        $this->stockForm = ['type' => $type, 'quantity' => $type === 'adjustment' ? (string) $item->current_stock : '', 'reason' => ''];
        $this->resetValidation();
    }

    public function closeStock(): void
    {
        $this->authorize('viewAny', InventoryItem::class);
        $this->reset('stockItemId', 'openingStock', 'stockForm', 'showForm');
        $this->resetValidation();
        $this->dispatch('inventory-stock-closed');
    }

    public function increaseStock(): void
    {
        $this->stepStock(1);
    }

    public function decreaseStock(): void
    {
        $this->stepStock(-1);
    }

    private function stepStock(int $step): void
    {
        abort_if($this->stockItemId === null, 404);
        $this->authorize('update', InventoryItem::query()->findOrFail($this->stockItemId));
        abort_unless($this->openingStock !== null && ($this->stockForm['type'] ?? null) === 'adjustment', 422);
        $value = $this->stockForm['quantity'] ?? null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError('stockForm.quantity', 'Masukkan stok akhir berupa bilangan bulat.');

            return;
        }
        $next = (int) $value + $step;
        if ($next < 0 || $next > StockLedger::MAX_STOCK) {
            $this->addError('stockForm.quantity', 'Stok akhir di luar batas.');

            return;
        }
        $this->stockForm['quantity'] = (string) $next;
        $this->resetValidation('stockForm.quantity');
    }

    public function saveStock(): void
    {
        $this->resetValidation();
        abort_if($this->stockItemId === null, 404);
        $item = InventoryItem::query()->findOrFail($this->stockItemId);
        $this->authorize('update', $item);
        $correction = $this->openingStock !== null;
        abort_unless(($this->stockForm['type'] ?? null) === ($correction ? 'adjustment' : 'in'), 422);
        $quantityRules = $correction
            ? ['required', 'integer', 'between:0,'.StockLedger::MAX_STOCK]
            : ['required', 'integer', 'between:1,'.StockLedger::MAX_STOCK];
        $data = $this->validate(['stockForm.type' => ['required', Rule::in(['in', 'adjustment'])], 'stockForm.quantity' => $quantityRules, 'stockForm.reason' => ['required', 'string', 'max:255']])['stockForm'];
        $delta = $correction ? (int) $data['quantity'] - $this->openingStock : (int) $data['quantity'];
        if ($delta === 0) {
            $this->addError('stockForm.quantity', 'Stok akhir sama dengan stok saat formulir dibuka.');

            return;
        }
        try {
            app(StockLedger::class)->move($this->actor(), $item, $delta, $data['type'], $data['reason'], expectedStock: $correction ? $this->openingStock : null);
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
