<?php

namespace App\Models;

use App\Enums\ServiceSource;
use App\Enums\ServiceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int|null $booking_id
 * @property int $id
 * @property int $customer_id
 * @property int $vehicle_id
 * @property int|null $mechanic_id
 * @property int $received_by
 * @property string $service_number
 * @property int $current_mileage
 * @property string $complaint
 * @property string|null $diagnosis
 * @property string|null $notes
 * @property ServiceStatus $status
 * @property ServiceSource $source
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $delivered_at
 */
#[Fillable(['service_number', 'customer_id', 'vehicle_id', 'mechanic_id', 'received_by', 'source', 'current_mileage', 'complaint', 'diagnosis', 'notes', 'status', 'received_at', 'started_at', 'completed_at', 'delivered_at'])]
class ServiceOrder extends Model
{
    /** @use HasFactory<ServiceOrderFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source' => ServiceSource::class,
            'status' => ServiceStatus::class,
            'current_mileage' => 'integer',
            'received_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<ServiceItem, $this> */
    public function parts(): HasMany
    {
        return $this->hasMany(ServiceItem::class);
    }

    /** @return HasMany<ServiceDocumentation, $this> */
    public function documentation(): HasMany
    {
        return $this->hasMany(ServiceDocumentation::class);
    }

    /** @return HasOne<Receipt, $this> */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    /** @return HasMany<ServiceJob, $this> */
    public function jobs(): HasMany
    {
        return $this->hasMany(ServiceJob::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mechanic_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')->withTrashed();
    }
}
