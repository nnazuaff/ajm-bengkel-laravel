<?php

namespace App\Actions;

use App\Enums\ServiceStatus;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ServiceItem;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UseServicePart
{
    private function assertReceiptUnlocked(ServiceOrder $order): void
    {
        if (DB::table('receipts')->where('service_order_id', $order->id)->whereIn('status', ['final', 'paid'])->exists()) {
            throw ValidationException::withMessages(['status' => 'Part terkunci oleh bon final atau lunas. Batalkan bon dahulu.']);
        }
    }

    public function returnPart(User $actor, ServiceOrder $order, ServiceItem $part): ServiceItem
    {
        return DB::transaction(function () use ($actor, $order, $part): ServiceItem {
            $currentOrder = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            Gate::forUser($actor)->authorize('update', $currentOrder);
            $this->assertReceiptUnlocked($currentOrder);
            $current = ServiceItem::query()->lockForUpdate()->findOrFail($part->id);
            if ($current->service_order_id !== $currentOrder->id) {
                throw new AuthorizationException('Part tidak berasal dari servis ini.');
            }
            $current->setRelation('serviceOrder', $currentOrder);
            Gate::forUser($actor)->authorize('update', $current);
            if ($current->returned_at !== null) {
                return $current;
            }
            if (in_array($currentOrder->status, [ServiceStatus::Completed, ServiceStatus::ReadyForPickup, ServiceStatus::Delivered, ServiceStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['status' => 'Pengembalian manual terkunci karena servis sudah ditutup.']);
            }

            return $this->reverse($actor, $current, 'return');
        }, attempts: 5);
    }

    public function returnAll(User $actor, ServiceOrder $order): void
    {
        DB::transaction(function () use ($actor, $order): void {
            $currentOrder = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            Gate::forUser($actor)->authorize('manage-workshop');
            $this->assertReceiptUnlocked($currentOrder);
            // Trusted lifecycle reversal: closed service permitted after cancellation/receipt void.
            $parts = ServiceItem::query()->where('service_order_id', $currentOrder->id)->whereNull('returned_at')->orderBy('inventory_item_id')->orderBy('id')->lockForUpdate()->get();
            InventoryItem::withTrashed()->whereIn('id', $parts->pluck('inventory_item_id'))->orderBy('id')->lockForUpdate()->get();
            foreach ($parts as $part) {
                $this->reverse($actor, $part, 'service_cancelled');
            }
        }, attempts: 5);
    }

    private function reverse(User $actor, ServiceItem $part, string $reason): ServiceItem
    {
        $item = InventoryItem::withTrashed()->lockForUpdate()->findOrFail($part->inventory_item_id);
        app(StockLedger::class)->move($actor, $item, $part->quantity, 'return', $reason, ServiceItem::class, $part->id);
        $part->returned_at = now();
        if (! $part->save()) {
            throw new \RuntimeException('Pengembalian gagal disimpan.');
        }
        $audit = AuditLog::create(['actor_id' => $actor->id, 'action' => 'service_part.returned', 'entity_type' => ServiceItem::class, 'entity_id' => $part->id, 'context' => ['service_order_id' => $part->service_order_id, 'quantity' => $part->quantity, 'reason' => $reason]]);
        if (! $audit->exists) {
            throw new \RuntimeException('Audit gagal disimpan.');
        }

        return $part;
    }

    public function use(User $actor, ServiceOrder $order, int $inventoryItemId, int $quantity): ServiceItem
    {
        return DB::transaction(function () use ($actor, $order, $inventoryItemId, $quantity): ServiceItem {
            $currentOrder = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            Gate::forUser($actor)->authorize('create', [ServiceItem::class, $currentOrder]);
            $this->assertReceiptUnlocked($currentOrder);
            if (in_array($currentOrder->status, [ServiceStatus::Completed, ServiceStatus::ReadyForPickup, ServiceStatus::Delivered, ServiceStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['status' => 'Part terkunci karena servis sudah selesai atau ditutup.']);
            }
            Validator::make(['quantity' => $quantity], ['quantity' => ['required', 'integer', 'min:1', 'max:'.StockLedger::MAX_STOCK]])->validate();
            $item = InventoryItem::withTrashed()->lockForUpdate()->findOrFail($inventoryItemId);
            if ($item->trashed() || ! $item->is_active) {
                throw ValidationException::withMessages(['inventory_item_id' => 'Part sudah tidak aktif atau diarsipkan.']);
            }
            if (! preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/', $item->selling_price)) {
                throw ValidationException::withMessages(['unit_price' => 'Harga part tidak valid.']);
            }
            $subtotal = bcmul($item->selling_price, (string) $quantity, 2);
            if (bccomp($subtotal, '999999999999.99', 2) > 0) {
                throw ValidationException::withMessages(['quantity' => 'Subtotal part melebihi batas.']);
            }
            $part = ServiceItem::create(['service_order_id' => $currentOrder->id, 'inventory_item_id' => $item->id, 'description' => $item->name, 'quantity' => $quantity, 'unit_price' => $item->selling_price, 'subtotal' => $subtotal, 'used_by' => $actor->id]);
            if (! $part->exists) {
                throw new \RuntimeException('Pemakaian part gagal disimpan.');
            }
            app(StockLedger::class)->move($actor, $item, -$quantity, 'out', 'service', ServiceItem::class, $part->id);
            $audit = AuditLog::create(['actor_id' => $actor->id, 'action' => 'service_part.used', 'entity_type' => ServiceItem::class, 'entity_id' => $part->id, 'context' => $part->only(['service_order_id', 'inventory_item_id', 'quantity', 'unit_price', 'subtotal'])]);
            if (! $audit->exists) {
                throw new \RuntimeException('Audit gagal disimpan.');
            }

            return $part;
        }, attempts: 5);
    }
}
