<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockLedger
{
    public const MAX_STOCK = 2147483647;

    public function move(User $actor, InventoryItem $item, int $delta, string $type, string $reason, ?string $referenceType = null, ?int $referenceId = null): StockMovement
    {
        return DB::transaction(function () use ($actor, $item, $delta, $type, $reason, $referenceType, $referenceId): StockMovement {
            Validator::make(['quantity' => $delta, 'type' => $type, 'reason' => trim($reason), 'reference_type' => $referenceType, 'reference_id' => $referenceId], [
                'quantity' => ['required', 'integer', 'between:'.(-self::MAX_STOCK).','.self::MAX_STOCK, 'not_in:0'], 'type' => ['required', Rule::in(['in', 'out', 'adjustment', 'return'])], 'reason' => ['required', 'string', 'max:255'], 'reference_type' => ['nullable', 'string', 'max:120'], 'reference_id' => ['nullable', 'integer', 'min:1'],
            ])->validate();
            if (($type === 'out' && $delta > 0) || (in_array($type, ['in', 'return'], true) && $delta < 0)) {
                throw ValidationException::withMessages(['quantity' => 'Arah jumlah tidak sesuai jenis mutasi.']);
            }
            $current = InventoryItem::withTrashed()->lockForUpdate()->findOrFail($item->id);
            $before = $current->current_stock;
            $after = $before + $delta;
            if ($after < 0 || $after > self::MAX_STOCK) {
                throw ValidationException::withMessages(['quantity' => 'Stok tidak cukup atau melebihi batas.']);
            }
            $current->current_stock = $after;
            if (! $current->save()) {
                throw new \RuntimeException('Stok gagal disimpan.');
            }
            $movement = StockMovement::create(['inventory_item_id' => $current->id, 'type' => $type, 'quantity' => $delta, 'stock_before' => $before, 'stock_after' => $after, 'reason' => trim($reason), 'reference_type' => $referenceType, 'reference_id' => $referenceId, 'created_by' => $actor->id]);
            if (! $movement->exists) {
                throw new \RuntimeException('Mutasi stok gagal disimpan.');
            }
            $audit = AuditLog::create(['actor_id' => $actor->id, 'action' => 'inventory.stock_changed', 'entity_type' => InventoryItem::class, 'entity_id' => $current->id, 'context' => ['movement_id' => $movement->id, 'before' => $before, 'after' => $after, 'quantity' => $delta, 'reason' => trim($reason)]]);
            if (! $audit->exists) {
                throw new \RuntimeException('Audit gagal disimpan.');
            }

            return $movement;
        }, attempts: 5);
    }
}
