<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int|null $customer_id
 * @property int|null $vehicle_id
 * @property int|null $submitted_by
 * @property string $booking_number
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string $license_plate
 * @property string $brand
 * @property string $model
 * @property int|null $year
 * @property int $current_mileage
 * @property CarbonImmutable $booking_date
 * @property string $arrival_time
 * @property string $service_type
 * @property string $complaint
 * @property string|null $notes
 * @property string|null $admin_notes
 * @property BookingStatus $status
 */
#[Fillable(['customer_id', 'vehicle_id', 'booking_number', 'submitted_by', 'name', 'phone', 'email', 'license_plate', 'brand', 'model', 'year', 'current_mileage', 'booking_date', 'arrival_time', 'service_type', 'complaint', 'notes', 'admin_notes', 'status'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['booking_date' => 'immutable_date', 'status' => BookingStatus::class, 'current_mileage' => 'integer', 'year' => 'integer', 'submitted_by' => 'integer'];
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
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    /** @return HasOne<ServiceOrder, $this> */
    public function serviceOrder(): HasOne
    {
        return $this->hasOne(ServiceOrder::class);
    }
}
