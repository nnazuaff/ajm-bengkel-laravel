<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveInventoryItem
{
    /** @param array<string,mixed> $input */
    public function save(User $actor, array $input, ?InventoryItem $item = null): InventoryItem
    {
        return DB::transaction(function () use ($actor, $input, $item): InventoryItem {
            $current = $item === null ? new InventoryItem : InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            Gate::forUser($actor)->authorize($item === null ? 'create' : 'update', $item === null ? InventoryItem::class : $current);
            $data = Validator::make($input, [
                'current_stock' => ['prohibited'],
                'sku' => ['required', 'string', 'max:60', Rule::unique('inventory_items', 'sku')->ignore($current->id)], 'name' => ['required', 'string', 'max:120'],
                'category_id' => ['nullable', 'integer', 'exists:inventory_categories,id'], 'brand' => ['nullable', 'string', 'max:120'],
                'purchase_price' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/'],
                'selling_price' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/'],
                'minimum_stock' => ['required', 'integer', 'min:0', 'max:'.StockLedger::MAX_STOCK], 'unit' => ['required', 'string', 'max:30'],
                'supplier' => ['nullable', 'string', 'max:120'], 'storage_location' => ['nullable', 'string', 'max:120'], 'is_active' => ['required', 'boolean'],
            ])->validate();
            $before = $item === null ? null : $current->only(array_keys($data));
            $current->fill($data);
            if (! $current->save()) {
                throw new \RuntimeException('Barang gagal disimpan.');
            }
            $current->refresh();
            $audit = AuditLog::create(['actor_id' => $actor->id, 'action' => $item === null ? 'inventory.created' : 'inventory.updated', 'entity_type' => InventoryItem::class, 'entity_id' => $current->id, 'context' => ['before' => $before, 'after' => $current->only(array_keys($data))]]);
            if (! $audit->exists) {
                throw new \RuntimeException('Audit gagal disimpan.');
            }
            if ($before !== null && ($before['purchase_price'] !== $current->purchase_price || $before['selling_price'] !== $current->selling_price)) {
                $audit = AuditLog::create(['actor_id' => $actor->id, 'action' => 'inventory.price_changed', 'entity_type' => InventoryItem::class, 'entity_id' => $current->id, 'context' => ['before' => ['purchase_price' => $before['purchase_price'], 'selling_price' => $before['selling_price']], 'after' => $current->only(['purchase_price', 'selling_price'])]]);
                if (! $audit->exists) {
                    throw new \RuntimeException('Audit gagal disimpan.');
                }
            }

            return $current;
        }, attempts: 5);
    }
}
