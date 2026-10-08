<?php

namespace App\Livewire;

use App\Actions\StockLedger;
use App\Actions\UseServicePart;
use App\Enums\ServiceStatus;
use App\Models\InventoryItem;
use App\Models\ServiceItem;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\WorkshopInput;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ServiceParts extends Component
{
    #[Locked]
    public int $serviceOrderId = 0;

    public string $search = '';

    public string $inventoryItemId = '';

    public string $quantity = '1';

    public function boot(): void
    {
        $this->authorize('viewAny', ServiceOrder::class);
        if ($this->serviceOrderId !== 0) {
            $this->order();
        }
    }

    public function mount(int $serviceOrderId): void
    {
        $this->serviceOrderId = $serviceOrderId;
        $this->order();
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function order(): ServiceOrder
    {
        $this->authorize('viewAny', ServiceOrder::class);
        $order = ServiceOrder::query()->findOrFail($this->serviceOrderId);
        $this->authorize('view', $order);

        return $order;
    }

    public function usePart(): void
    {
        $order = $this->order();
        $this->authorize('create', [ServiceItem::class, $order]);
        $this->resetValidation();
        $data = $this->validate(['inventoryItemId' => ['required', 'integer', 'min:1'], 'quantity' => ['required', 'integer', 'min:1', 'max:'.StockLedger::MAX_STOCK]]);
        try {
            app(UseServicePart::class)->use($this->actor(), $order, (int) $data['inventoryItemId'], (int) $data['quantity']);
        } catch (ValidationException $e) {
            $this->actionErrors($e);

            return;
        }
        $this->reset('inventoryItemId', 'quantity', 'search');
        session()->flash('partStatus', 'Part terpakai; stok dan riwayat tercatat.');
    }

    public function returnPart(int $id): void
    {
        $order = $this->order();
        $part = ServiceItem::query()->where('service_order_id', $order->id)->findOrFail($id);
        $this->resetValidation();
        try {
            app(UseServicePart::class)->returnPart($this->actor(), $order, $part);
        } catch (ValidationException $e) {
            $this->actionErrors($e);

            return;
        }
        session()->flash('partStatus', 'Part dikembalikan. Riwayat pemakaian tetap tersimpan.');
    }

    private function actionErrors(ValidationException $e): void
    {
        foreach ($e->errors() as $key => $messages) {
            foreach ($messages as $message) {
                $this->addError($key === 'inventory_item_id' ? 'inventoryItemId' : $key, $message);
            }
        }
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
        $order = $this->order();
        $search = Str::lower(Str::squish(Str::substr($this->search, 0, 120)));
        $matches = InventoryItem::query()->where('is_active', true)->where('current_stock', '>', 0)
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [WorkshopInput::like($search)])->orWhereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [WorkshopInput::like($search)])))
            ->orderBy('name')->limit(31)->get();
        $parts = ServiceItem::query()->where('service_order_id', $order->id)->with(['inventoryItem', 'user'])->orderByDesc('id')->get();
        $subtotal = '0.00';
        foreach ($parts as $part) {
            if ($part->returned_at === null) {
                $subtotal = bcadd($subtotal, $part->subtotal, 2);
            }
        }

        return view('livewire.service-parts', ['parts' => $parts, 'matches' => $matches->take(30), 'refineSearch' => $matches->count() > 30, 'partsSubtotal' => $this->formatMoney($subtotal),
            'locked' => in_array($order->status, [ServiceStatus::Completed, ServiceStatus::ReadyForPickup, ServiceStatus::Delivered, ServiceStatus::Cancelled], true) || DB::table('receipts')->where('service_order_id', $order->id)->whereIn('status', ['final', 'paid'])->exists()]);
    }
}
