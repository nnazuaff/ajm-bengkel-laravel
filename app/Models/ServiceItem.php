<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ServiceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $service_order_id
 * @property int $inventory_item_id
 * @property string $description
 * @property int $quantity
 * @property numeric-string $unit_price
 * @property numeric-string $subtotal
 * @property int $used_by
 * @property CarbonImmutable|null $returned_at
 */
#[Fillable(['service_order_id', 'inventory_item_id', 'description', 'quantity', 'unit_price', 'subtotal', 'used_by'])]
class ServiceItem extends Model
{
    /** @use HasFactory<ServiceItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (ServiceItem $part): void {
            if (array_diff(array_keys($part->getDirty()), ['returned_at', 'updated_at']) !== [] || ($part->isDirty('returned_at') && ($part->getOriginal('returned_at') !== null || $part->returned_at === null))) {
                throw new \LogicException('Snapshot part tidak dapat diubah.');
            }
        });
        static::deleting(function (): never {
            throw new \LogicException('Part servis tidak dapat dihapus.');
        });
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'decimal:2', 'subtotal' => 'decimal:2', 'returned_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ServiceOrder,$this> */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /** @return BelongsTo<InventoryItem,$this> */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class)->withTrashed();
    }

    /** @return BelongsTo<User,$this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by')->withTrashed();
    }
}
