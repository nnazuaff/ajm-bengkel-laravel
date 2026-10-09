<?php

namespace App\Livewire;

use App\Models\Receipt;
use App\Models\ServiceOrder;
use App\Support\WorkshopInput;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Bon & penjualan')]
class Receipts extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $status = '';

    public string $paymentStatus = '';

    public string $date = '';

    public function boot(): void
    {
        $this->authorize('viewAny', Receipt::class);
    }

    public function mount(): void
    {
        if (request()->filled('service_order_id')) {
            $order = ServiceOrder::findOrFail(request()->integer('service_order_id'));
            $this->authorize('view', $order);
            $receipt = Receipt::where('service_order_id', $order->id)->first();
            if ($receipt) {
                $this->authorize('view', $receipt);
                $this->redirectRoute('receipts.edit', ['receipt' => $receipt->id]);
            } else {
                if (! in_array($order->status->value, ['completed', 'ready_for_pickup', 'delivered'], true)) {
                    session()->flash('status', 'Bon dapat dibuat setelah servis selesai.');
                    $this->redirectRoute('services.detail', ['serviceOrder' => $order->id]);

                    return;
                }
                $this->authorize('create', Receipt::class);
                $this->redirectRoute('receipts.create', ['service_order_id' => $order->id]);
            }
        } elseif (request()->filled('receipt_id')) {
            $receipt = Receipt::findOrFail(request()->integer('receipt_id'));
            $this->authorize('view', $receipt);
            $this->redirectRoute('receipts.edit', ['receipt' => $receipt->id]);
        }
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'status', 'paymentStatus', 'date'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->authorize('viewAny', Receipt::class);
        $term = WorkshopInput::like(mb_substr(trim($this->search), 0, 120));

        return view('livewire.receipts', [
            'receipts' => Receipt::with(['customer', 'vehicle'])->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw("receipt_number LIKE ? ESCAPE '!'", [$term])->orWhereHas('customer', fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$term]))))->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->when($this->paymentStatus !== '', fn ($q) => $q->where('payment_status', $this->paymentStatus))->when($this->date !== '', fn ($q) => $q->whereDate('transaction_date', $this->date))->latest('id')->paginate(15),
        ]);
    }
}
