<?php

namespace App\Models;

use App\Support\WorkshopInput;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $notes
 */
#[Fillable(['name', 'phone', 'email', 'address', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    /** @param Builder<Customer> $query */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $term = Str::squish($term);
        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [WorkshopInput::like(Str::lower($term))])
                ->orWhereRaw("phone LIKE ? ESCAPE '!'", [WorkshopInput::like(WorkshopInput::phone($term))])
                ->orWhereHas('vehicles', fn (Builder $vehicles) => $vehicles
                    ->whereRaw("license_plate LIKE ? ESCAPE '!'", [WorkshopInput::like(WorkshopInput::plate($term))]));
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return HasMany<ServiceOrder, $this> */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    /** @return HasMany<Vehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = Str::squish($value);
    }

    public function setPhoneAttribute(string $value): void
    {
        $this->attributes['phone'] = WorkshopInput::phone($value);
    }
}
