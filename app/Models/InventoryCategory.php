<?php

namespace App\Models;

use Database\Factories\InventoryCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property string $name */
#[Fillable(['name'])]
class InventoryCategory extends Model
{
    /** @use HasFactory<InventoryCategoryFactory> */
    use HasFactory;

    /** @return HasMany<InventoryItem,$this> */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'category_id');
    }
}
