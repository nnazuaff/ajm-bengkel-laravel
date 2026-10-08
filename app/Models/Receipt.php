<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\ReceiptStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property string $receipt_number
 * @property int|null $service_order_id
 * @property int|null $customer_id
 * @property int|null $vehicle_id
 * @property int $cashier_id
 * @property CarbonImmutable $transaction_date
 * @property ReceiptStatus $status
 * @property PaymentStatus $payment_status
 * @property numeric-string $subtotal
 * @property numeric-string $discount
 * @property numeric-string $grand_total
 * @property string|null $notes
 * @property array<string,string|null>|null $workshop_snapshot
 * @property array<string,string|null>|null $customer_snapshot
 * @property array<string,string|null>|null $vehicle_snapshot
 * @property string|null $void_reason
 * @property CarbonImmutable|null $voided_at
 * @property-read Collection<int,ReceiptItem> $items
 * @property-read Collection<int,Payment> $payments
 * @property-read Customer|null $customer
 * @property-read Vehicle|null $vehicle
 * @property-read ServiceOrder|null $serviceOrder
 */
#[Fillable(['public_id', 'receipt_number', 'service_order_id', 'customer_id', 'vehicle_id', 'cashier_id', 'transaction_date', 'status', 'payment_status', 'subtotal', 'discount', 'grand_total', 'notes', 'workshop_snapshot', 'customer_snapshot', 'vehicle_snapshot', 'void_reason', 'voided_at'])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['status' => ReceiptStatus::class, 'payment_status' => PaymentStatus::class, 'transaction_date' => 'immutable_datetime', 'voided_at' => 'immutable_datetime', 'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'grand_total' => 'decimal:2', 'workshop_snapshot' => 'array', 'customer_snapshot' => 'array', 'vehicle_snapshot' => 'array'];
    }

    /** @return HasMany<ReceiptItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReceiptItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return BelongsTo<ServiceOrder, $this> */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
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
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id')->withTrashed();
    }
}
