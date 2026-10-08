<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property int|null $category_id
 * @property string|null $brand
 * @property numeric-string $purchase_price
 * @property numeric-string $selling_price
 * @property int $current_stock
 * @property int $minimum_stock
 * @property string $unit
 * @property string|null $supplier
 * @property string|null $storage_location
 * @property bool $is_active
 */
#[Fillable(['sku', 'name', 'category_id', 'brand', 'purchase_price', 'selling_price', 'minimum_stock', 'unit', 'supplier', 'storage_location', 'is_active'])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['purchase_price' => 'decimal:2', 'selling_price' => 'decimal:2', 'current_stock' => 'integer', 'minimum_stock' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<InventoryCategory,$this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    /** @return HasMany<StockMovement,$this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return HasMany<ServiceItem,$this> */
    public function serviceItems(): HasMany
    {
        return $this->hasMany(ServiceItem::class);
    }
}
