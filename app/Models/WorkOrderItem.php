<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['work_order_id', 'sparepart_id', 'service_name', 'quantity', 'unit_price', 'subtotal'])]
class WorkOrderItem extends Model
{
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function sparepart(): BelongsTo
    {
        return $this->belongsTo(Sparepart::class);
    }

    public function servicePhotos(): HasMany
    {
        return $this->hasMany(ServicePhoto::class);
    }
}
