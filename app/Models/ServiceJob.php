<?php

namespace App\Models;

use App\Enums\ServiceJobStatus;
use Database\Factories\ServiceJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $service_order_id
 * @property string $name
 * @property string|null $description
 * @property int|null $mechanic_id
 * @property string $labor_price
 * @property ServiceJobStatus $status
 * @property string|null $notes
 */
#[Fillable(['service_order_id', 'name', 'description', 'mechanic_id', 'labor_price', 'status', 'notes'])]
class ServiceJob extends Model
{
    /** @use HasFactory<ServiceJobFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['labor_price' => 'decimal:2', 'status' => ServiceJobStatus::class, 'mechanic_id' => 'integer'];
    }

    /** @return BelongsTo<ServiceOrder, $this> */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mechanic_id')->withTrashed();
    }
}
