<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inventory_item_id
 * @property int $quantity
 * @property int $stock_before
 * @property int $stock_after
 * @property string $type
 * @property string $reason
 */
#[Fillable(['inventory_item_id', 'type', 'quantity', 'stock_before', 'stock_after', 'reason', 'reference_type', 'reference_id', 'created_by'])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Mutasi stok tidak dapat diubah.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Mutasi stok tidak dapat dihapus.');
        });
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'stock_before' => 'integer', 'stock_after' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<InventoryItem,$this> */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class)->withTrashed();
    }

    /** @return BelongsTo<User,$this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
