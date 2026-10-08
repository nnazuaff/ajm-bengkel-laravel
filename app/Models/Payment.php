<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $receipt_id
 * @property numeric-string $amount
 * @property PaymentMethod $method
 * @property CarbonImmutable $paid_at
 * @property string|null $reference
 * @property int $created_by
 * @property CarbonImmutable|null $reversed_at
 * @property string|null $reversal_reason
 * @property-read Receipt $receipt
 * @property-read User $creator
 */
#[Fillable(['receipt_id', 'amount', 'method', 'paid_at', 'reference', 'created_by', 'reversed_at', 'reversal_reason'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'method' => PaymentMethod::class, 'paid_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
