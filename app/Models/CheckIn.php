<?php

namespace App\Models;

use App\Enums\CheckInStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $requires_identity_verification
 * @property CheckInStatus $status
 * @property int $customer_id
 * @property int|null $service_order_id
 * @property CarbonImmutable|null $browser_access_expires_at
 * @property CarbonImmutable $checked_in_at
 * @property-read Customer $customer
 */
class CheckIn extends Model
{
    protected $guarded = [];

    protected $hidden = ['browser_token_hash'];

    protected function casts(): array
    {
        return ['requires_identity_verification' => 'boolean', 'status' => CheckInStatus::class, 'checked_in_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime', 'browser_access_expires_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<ServiceOrder, $this> */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}
