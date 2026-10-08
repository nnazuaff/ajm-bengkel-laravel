<?php

namespace App\Actions;

use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Enums\ServiceStatus;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkshopSetting;
use App\Support\ReceiptImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageReceipt
{
    private const MONEY = '/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/';

    /** @param array<string, mixed> $input */
    public function create(User $actor, array $input): Receipt
    {
        Gate::forUser($actor)->authorize('create', Receipt::class);

        return DB::transaction(function () use ($actor, $input): Receipt {
            $data = Validator::make($input, [
                'service_order_id' => ['nullable', 'integer', 'exists:service_orders,id'],
                'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
                'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
                'transaction_date' => ['nullable', 'date', 'before_or_equal:now'],
            ])->validate();
            $order = empty($data['service_order_id']) ? null : ServiceOrder::query()->whereKey($data['service_order_id'])->lockForUpdate()->firstOrFail();
            if ($order) {
                $this->completed($order);
                if (Receipt::where('service_order_id', $order->id)->exists()) {
                    throw ValidationException::withMessages(['service_order_id' => 'Servis sudah memiliki bon, termasuk bon dibatalkan.']);
                }
                $data['customer_id'] = $order->customer_id;
                $data['vehicle_id'] = $order->vehicle_id;
            } elseif (! empty($data['vehicle_id'])) {
                $vehicle = Vehicle::whereKey($data['vehicle_id'])->firstOrFail();
                if (! empty($data['customer_id']) && $vehicle->customer_id !== (int) $data['customer_id']) {
                    throw ValidationException::withMessages(['vehicle_id' => 'Kendaraan tidak dimiliki pelanggan ini.']);
                }
                $data['customer_id'] = $vehicle->customer_id;
            }
            $receipt = Receipt::create([...$data, 'public_id' => (string) Str::uuid(),
                'receipt_number' => app(NextServiceNumber::class)->generate('BON'), 'cashier_id' => $actor->id,
                'transaction_date' => $data['transaction_date'] ?? now(), 'status' => 'draft', 'payment_status' => 'unpaid',
            ]);
            $this->draft($receipt, $input, $order);
            $this->audit($actor, 'receipt.created', $receipt);

            return $receipt->refresh();
        }, attempts: 5);
    }

    /** @param array<string, mixed> $input */
    public function saveDraft(User $actor, Receipt $receipt, array $input): Receipt
    {
        return DB::transaction(function () use ($actor, $receipt, $input): Receipt {
            [$current, $order] = $this->locked($receipt);
            Gate::forUser($actor)->authorize('update', $current);
            $this->requireDraft($current);
            if (! array_key_exists('items', $input)) {
                $input['items'] = $current->items()->when($order !== null, fn ($query) => $query->where('type', 'custom'))
                    ->get(['type', 'description', 'quantity', 'unit_price', 'inventory_item_id'])->toArray();
            }
            $this->draft($current, $input, $order);
            $this->audit($actor, 'receipt.draft_updated', $current);

            return $current->refresh();
        }, attempts: 5);
    }

    public function finalize(User $actor, Receipt $receipt): Receipt
    {
        return DB::transaction(function () use ($actor, $receipt): Receipt {
            [$current, $order] = $this->locked($receipt);
            Gate::forUser($actor)->authorize('finalize', $current);
            $this->requireDraft($current);
            $editable = $current->items()->when($order !== null, fn ($query) => $query->where('type', 'custom'))
                ->get(['type', 'description', 'quantity', 'unit_price', 'inventory_item_id'])->toArray();
            $this->draft($current, ['items' => $editable], $order);
            if (! $current->items()->exists()) {
                throw ValidationException::withMessages(['items' => 'Tambahkan setidaknya satu baris.']);
            }
            if (! $order) {
                $this->stock($actor, $current, false);
            }
            $current->update([
                'status' => ReceiptStatus::Final,
                'workshop_snapshot' => WorkshopSetting::current()->only(['name', 'phone', 'address', 'receipt_footer', 'logo_path']),
                'customer_snapshot' => $current->customer?->only(['name', 'phone', 'address']) ?? ['name' => 'Umum'],
                'vehicle_snapshot' => $current->vehicle?->only(['license_plate', 'brand', 'model']),
            ]);
            $this->paymentState($current);
            // Validate mandatory output before committing stock and document locks.
            app(ReceiptImage::class)->render($current);
            $this->audit($actor, 'receipt.finalized', $current, ['grand_total' => $current->grand_total]);

            return $current->refresh();
        }, attempts: 5);
    }

    /** @param array<string, mixed> $input */
    public function pay(User $actor, Receipt $receipt, array $input): Payment
    {
        return DB::transaction(function () use ($actor, $receipt, $input): Payment {
            [$current] = $this->locked($receipt);
            Gate::forUser($actor)->authorize('pay', $current);
            if ($current->status !== ReceiptStatus::Final) {
                throw ValidationException::withMessages(['receipt' => 'Pembayaran hanya untuk bon final yang belum lunas.']);
            }
            $data = Validator::make($input, [
                'amount' => ['required', 'string', 'regex:'.self::MONEY],
                'method' => ['required', Rule::enum(PaymentMethod::class)],
                'paid_at' => ['required', 'date', 'before_or_equal:now', 'after_or_equal:'.$current->transaction_date->toDateString()],
                'reference' => ['nullable', 'string', 'max:255'],
            ])->validate();
            $balance = bcsub($current->grand_total, $this->paid($current), 2);
            if (bccomp($data['amount'], '0', 2) <= 0 || bccomp($data['amount'], $balance, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Pembayaran harus positif dan tidak melebihi sisa tagihan '.$balance.'.']);
            }
            $payment = $current->payments()->create([...$data, 'created_by' => $actor->id]);
            $this->paymentState($current);
            $this->audit($actor, 'payment.created', $current, ['payment_id' => $payment->id, 'amount' => $payment->amount, 'method' => $payment->method->value]);

            return $payment;
        }, attempts: 5);
    }

    public function reversePayment(User $actor, Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($actor, $payment, $reason): Payment {
            $receiptId = Payment::whereKey($payment->id)->value('receipt_id');
            [$receipt] = $this->locked(Receipt::whereKey($receiptId)->firstOrFail());
            Gate::forUser($actor)->authorize('reversePayment', $receipt);
            $reason = $this->reason($reason);
            $current = $receipt->payments()->lockForUpdate()->findOrFail($payment->id);
            if ($current->reversed_at !== null) {
                return $current;
            }
            $current->update(['reversed_at' => now(), 'reversal_reason' => $reason]);
            if ($receipt->status !== ReceiptStatus::Voided) {
                $this->paymentState($receipt);
            }
            $this->audit($actor, 'payment.reversed', $receipt, ['payment_id' => $current->id, 'amount' => $current->amount, 'reason' => $reason]);

            return $current->refresh();
        }, attempts: 5);
    }

    public function void(User $actor, Receipt $receipt, string $reason): Receipt
    {
        return DB::transaction(function () use ($actor, $receipt, $reason): Receipt {
            [$current, $order] = $this->locked($receipt);
            Gate::forUser($actor)->authorize('void', $current);
            $reason = $this->reason($reason);
            if ($current->status === ReceiptStatus::Voided) {
                return $current;
            }
            $wasDraft = $current->status === ReceiptStatus::Draft;
            $current->update(['status' => ReceiptStatus::Voided, 'payment_status' => 'unpaid', 'void_reason' => $reason, 'voided_at' => now()]);
            foreach ($current->payments()->whereNull('reversed_at')->orderBy('id')->lockForUpdate()->get() as $payment) {
                $payment->update(['reversed_at' => now(), 'reversal_reason' => $reason]);
                $this->audit($actor, 'payment.reversed', $current, ['payment_id' => $payment->id, 'amount' => $payment->amount, 'reason' => $reason]);
            }
            if (! $wasDraft) {
                if ($order) {
                    // Mark voided before trusted returnAll so final-document guards no longer block.
                    app(UseServicePart::class)->returnAll($actor, $order);
                } else {
                    $this->stock($actor, $current, true);
                }
            }
            $this->audit($actor, 'receipt.voided', $current, ['reason' => $reason, 'bookkeeping_only' => true]);

            return $current->refresh();
        }, attempts: 5);
    }

    private function reason(string $reason): string
    {
        return Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'max:2000']])->validate()['reason'];
    }

    /** @return numeric-string */
    private function paid(Receipt $receipt): string
    {
        $total = '0.00';
        foreach ($receipt->payments()->whereNull('reversed_at')->get(['amount']) as $payment) {
            $total = bcadd($total, $payment->amount, 2);
        }

        return $total;
    }

    private function paymentState(Receipt $receipt): void
    {
        $paid = $this->paid($receipt);
        $complete = bccomp($paid, $receipt->grand_total, 2) >= 0;
        $receipt->update(['status' => $complete ? 'paid' : 'final',
            'payment_status' => $complete ? 'paid' : (bccomp($paid, '0', 2) > 0 ? 'partial' : 'unpaid')]);
    }

    private function stock(User $actor, Receipt $receipt, bool $return): void
    {
        $quantities = [];
        foreach ($receipt->items()->whereNotNull('inventory_item_id')->get() as $line) {
            $quantities[$line->inventory_item_id] = ($quantities[$line->inventory_item_id] ?? 0) + $line->quantity;
        }
        ksort($quantities);
        foreach ($quantities as $id => $quantity) {
            $item = InventoryItem::withTrashed()->lockForUpdate()->findOrFail($id);
            if (! $return && (! $item->is_active || $item->trashed())) {
                throw ValidationException::withMessages(['items' => 'Produk tidak aktif atau sudah diarsipkan.']);
            }
            app(StockLedger::class)->move($actor, $item, $return ? $quantity : -$quantity,
                $return ? 'return' : 'out', $return ? 'sale_voided' : 'direct_sale', Receipt::class, $receipt->id);
        }
    }

    /** @return array{Receipt, ?ServiceOrder} */
    private function locked(Receipt $receipt): array
    {
        // Read the immutable parent key before locks: parent, receipt, inventory ascending.
        $parentId = Receipt::whereKey($receipt->id)->value('service_order_id');
        $order = $parentId ? ServiceOrder::query()->whereKey($parentId)->lockForUpdate()->firstOrFail() : null;

        return [Receipt::query()->lockForUpdate()->findOrFail($receipt->id), $order];
    }

    private function requireDraft(Receipt $receipt): void
    {
        if ($receipt->status !== ReceiptStatus::Draft) {
            throw ValidationException::withMessages(['receipt' => 'Bon final terkunci. Gunakan pembatalan untuk koreksi.']);
        }
    }

    private function completed(ServiceOrder $order): void
    {
        if (! in_array($order->status, [ServiceStatus::Completed, ServiceStatus::ReadyForPickup, ServiceStatus::Delivered], true)) {
            throw ValidationException::withMessages(['service_order_id' => 'Bon hanya untuk servis yang sudah selesai.']);
        }
    }

    /** @param array<string, mixed> $input */
    private function draft(Receipt $receipt, array $input, ?ServiceOrder $order): void
    {
        $data = Validator::make($input, [
            'discount' => ['sometimes', 'required', 'string', 'regex:'.self::MONEY],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['sometimes', 'array', 'max:100'],
            'items.*.type' => ['required', Rule::in($order ? ['custom'] : ['product', 'custom'])],
            'items.*.description' => ['required_if:items.*.type,custom', 'nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.inventory_item_id' => ['required_if:items.*.type,product', 'nullable', 'integer'],
            'items.*.unit_price' => ['required_if:items.*.type,custom', 'nullable', 'string', 'regex:'.self::MONEY],
        ])->validate();
        $lines = [];
        if ($order) {
            $this->completed($order);
            foreach ($order->jobs()->where('status', 'completed')->orderBy('id')->get() as $job) {
                $lines[] = ['type' => 'service', 'description' => $job->name, 'quantity' => 1, 'unit_price' => $job->labor_price, 'service_job_id' => $job->id];
            }
            foreach ($order->parts()->whereNull('returned_at')->orderBy('id')->get() as $part) {
                $lines[] = ['type' => 'product', 'description' => $part->description, 'quantity' => $part->quantity, 'unit_price' => $part->unit_price, 'inventory_item_id' => $part->inventory_item_id];
            }
        }
        $ids = [];
        foreach ($data['items'] ?? [] as $line) {
            if ($line['type'] === 'product') {
                $ids[] = $line['inventory_item_id'];
            }
        }
        $ids = array_unique($ids);
        sort($ids);
        $products = InventoryItem::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($data['items'] ?? [] as $line) {
            if ($line['type'] === 'product') {
                $item = $products->get($line['inventory_item_id']);
                if (! $item || ! $item->is_active) {
                    throw ValidationException::withMessages(['items' => 'Produk tidak aktif atau tidak tersedia.']);
                }
                $lines[] = ['type' => 'product', 'description' => $item->name, 'quantity' => (int) $line['quantity'], 'unit_price' => $item->selling_price, 'inventory_item_id' => $item->id];
            } else {
                $lines[] = ['type' => 'custom', 'description' => $line['description'], 'quantity' => (int) $line['quantity'], 'unit_price' => $line['unit_price']];
            }
        }
        if (count($lines) > 100) {
            throw ValidationException::withMessages(['items' => 'Maksimal 100 baris per bon.']);
        }
        $subtotal = '0.00';
        foreach ($lines as &$line) {
            $line['total'] = bcmul($line['unit_price'], (string) $line['quantity'], 2);
            $line['discount'] = '0.00';
            $subtotal = bcadd($subtotal, $line['total'], 2);
        }
        unset($line);
        if (bccomp($subtotal, '999999999999.99', 2) > 0) {
            throw ValidationException::withMessages(['items' => 'Total bon melebihi batas nilai.']);
        }
        $discount = $data['discount'] ?? $receipt->discount ?? '0.00';
        if (bccomp($discount, $subtotal, 2) > 0) {
            throw ValidationException::withMessages(['discount' => 'Diskon tidak boleh melebihi subtotal.']);
        }
        $receipt->items()->delete(); // Only draft rows are replaceable; finalized document never edited.
        $receipt->items()->createMany($lines);
        $receipt->update(['subtotal' => $subtotal, 'discount' => $discount, 'grand_total' => bcsub($subtotal, $discount, 2), 'notes' => $data['notes'] ?? $receipt->notes]);
    }

    /** @param array<string, mixed> $context */
    private function audit(User $actor, string $action, Receipt $receipt, array $context = []): void
    {
        AuditLog::create(['actor_id' => $actor->id, 'action' => $action, 'entity_type' => Receipt::class, 'entity_id' => $receipt->id, 'context' => $context]);
    }
}
