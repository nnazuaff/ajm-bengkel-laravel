<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'sku', 'category', 'stock', 'min_stock', 'unit_price'])]
class Sparepart extends Model
{
    public function workOrderItems(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }
}
