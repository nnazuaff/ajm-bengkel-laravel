<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $receipt_id
 * @property string $description
 * @property string $type
 * @property int $quantity
 * @property numeric-string $unit_price
 * @property numeric-string $discount
 * @property numeric-string $total
 * @property int|null $inventory_item_id
 * @property int|null $service_job_id
 */
#[Fillable(['receipt_id', 'description', 'type', 'quantity', 'unit_price', 'discount', 'total', 'inventory_item_id', 'service_job_id'])]
class ReceiptItem extends Model
{
    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2'];
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class)->withTrashed();
    }

    /** @return BelongsTo<ServiceJob, $this> */
    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class);
    }
}
