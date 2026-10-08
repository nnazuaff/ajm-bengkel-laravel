<?php

namespace App\Livewire;

use App\Actions\ManageReceipt;
use App\Enums\ReceiptStatus;
use App\Models\Customer;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceOrder;
use App\Support\WorkshopInput;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Bon · editor')]
class ReceiptEditor extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public ?int $editingId = null;

    public string $customerId = '';

    public string $serviceOrderId = '';

    public string $discount = '0.00';

    public string $notes = '';

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public string $productSearch = '';

    public string $categoryId = '';

    public string $amount = '';

    public string $method = 'cash';

    public string $paidAt = '';

    public string $reference = '';

    public string $reason = '';

    public function boot(): void
    {
        $this->authorize('viewAny', Receipt::class);
        if ($this->editingId !== null) {
            $this->currentReceipt();
        }
    }

    public function mount(?int $receipt = null): void
    {
        $this->paidAt = now()->format('Y-m-d\TH:i');
        if ($receipt !== null) {
            $this->loadReceipt($receipt);

            return;
        }

        $this->authorize('create', Receipt::class);
        if (request()->filled('service_order_id')) {
            $order = ServiceOrder::findOrFail(request()->integer('service_order_id'));
            $this->authorize('view', $order);
            $existing = Receipt::where('service_order_id', $order->id)->first();
            if ($existing) {
                $this->authorize('view', $existing);
                $this->redirectRoute('receipts.edit', ['receipt' => $existing->id]);

                return;
            }
            abort_unless(in_array($order->status->value, ['completed', 'ready_for_pickup', 'delivered'], true), 422);
            $this->serviceOrderId = (string) $order->id;
        }
    }

    private function currentReceipt(): Receipt
    {
        abort_if($this->editingId === null, 404);
        $receipt = Receipt::findOrFail($this->editingId);
        $this->authorize('view', $receipt);

        return $receipt;
    }

    private function loadReceipt(int $id): void
    {
        $receipt = Receipt::with('items')->findOrFail($id);
        $this->authorize('view', $receipt);
        $this->editingId = $id;
        $this->serviceOrderId = (string) ($receipt->service_order_id ?? '');
        $this->customerId = (string) ($receipt->customer_id ?? '');
        $this->discount = $receipt->discount;
        $this->notes = $receipt->notes ?? '';
        $this->items = $receipt->items->filter(fn ($line) => ! $receipt->service_order_id || $line->type === 'custom')
            ->map(fn ($line) => $line->only(['type', 'description', 'quantity', 'unit_price', 'inventory_item_id']))->values()->all();
        $this->reset(['reason', 'amount', 'reference']);
        $this->resetValidation();
    }

    private function authorizeDraft(): void
    {
        if ($this->editingId === null) {
            $this->authorize('create', Receipt::class);

            return;
        }
        $receipt = $this->currentReceipt();
        $this->authorize('update', $receipt);
        if ($receipt->status !== ReceiptStatus::Draft) {
            throw ValidationException::withMessages(['receipt' => 'Bon final terkunci. Gunakan pembatalan untuk koreksi.']);
        }
    }

    public function addItem(string $type = 'custom'): void
    {
        $this->authorizeDraft();
        if (! in_array($type, ['custom', 'product'], true) || count($this->items) >= 100) {
            throw ValidationException::withMessages(['items' => 'Jenis baris tidak valid atau maksimal 100 baris.']);
        }
        $this->items[] = ['type' => $type, 'description' => '', 'quantity' => 1, 'unit_price' => '0.00', 'inventory_item_id' => null];
    }

    public function removeItem(int $index): void
    {
        $this->authorizeDraft();
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        $this->authorizeDraft();
        $new = $this->editingId === null;
        $this->validate(['items' => ['array', 'max:100'], 'items.*' => ['array']]);
        $action = app(ManageReceipt::class);
        $input = ['discount' => $this->discount, 'notes' => $this->notes, 'items' => $this->items];
        $receipt = $this->editingId ? $action->saveDraft(Auth::user(), $this->currentReceipt(), $input)
            : $action->create(Auth::user(), [...$input, 'customer_id' => $this->customerId ?: null, 'service_order_id' => $this->serviceOrderId ?: null]);
        $this->loadReceipt($receipt->id);
        session()->flash('status', 'Draf bon disimpan. Harga dan total dihitung server.');
        if ($new) {
            $this->redirectRoute('receipts.edit', ['receipt' => $receipt->id], navigate: true);
        }
    }

    public function finalize(): void
    {
        $this->authorize('finalize', $this->currentReceipt());
        app(ManageReceipt::class)->finalize(Auth::user(), $this->currentReceipt());
        $this->loadReceipt($this->editingId);
        session()->flash('status', 'Bon final terkunci. Stok penjualan langsung telah dicatat.');
    }

    public function pay(): void
    {
        $this->authorize('pay', $this->currentReceipt());
        app(ManageReceipt::class)->pay(Auth::user(), $this->currentReceipt(), ['amount' => $this->amount, 'method' => $this->method, 'paid_at' => $this->paidAt, 'reference' => $this->reference]);
        $this->loadReceipt($this->editingId);
        session()->flash('status', 'Pembayaran dicatat.');
    }

    public function reversePayment(int $id): void
    {
        $this->authorize('reversePayment', $this->currentReceipt());
        $payment = Payment::findOrFail($id);
        if ($payment->receipt_id !== $this->editingId) {
            abort(403);
        }
        app(ManageReceipt::class)->reversePayment(Auth::user(), $payment, $this->reason);
        $this->loadReceipt($this->editingId);
        session()->flash('status', 'Pembayaran dibalik dalam pembukuan. Tidak ada refund bank otomatis.');
    }

    public function void(): void
    {
        $this->authorize('void', $this->currentReceipt());
        app(ManageReceipt::class)->void(Auth::user(), $this->currentReceipt(), $this->reason);
        $this->loadReceipt($this->editingId);
        session()->flash('status', 'Bon dibatalkan, stok dikembalikan. Koreksi pembukuan bukan refund bank.');
    }

    public function render(): View
    {
        $this->authorize('viewAny', Receipt::class);
        $selected = $this->editingId ? $this->currentReceipt()->load(['items', 'payments.creator', 'customer', 'serviceOrder']) : null;
        $paid = '0.00';
        foreach ($selected !== null ? $selected->payments : [] as $payment) {
            if (! $payment->reversed_at) {
                $paid = bcadd($paid, $payment->amount, 2);
            }
        }

        return view('livewire.receipt-editor', [
            'selected' => $selected, 'paid' => $paid,
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'phone']),
            'services' => ServiceOrder::whereIn('status', ['completed', 'ready_for_pickup', 'delivered'])->with('customer')->orderByDesc('id')->get(),
            'products' => InventoryItem::where('is_active', true)->when($this->productSearch !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', WorkshopInput::like(mb_substr($this->productSearch, 0, 120)))->orWhere('sku', 'like', WorkshopInput::like(mb_substr($this->productSearch, 0, 120)))))->when($this->categoryId !== '', fn ($q) => $q->where('category_id', $this->categoryId))->orderBy('name')->get(),
            'categories' => InventoryCategory::orderBy('name')->get(),
        ]);
    }
}
