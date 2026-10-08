<?php

namespace App\Models;

use App\Support\WorkshopInput;
use Database\Factories\VehicleFactory;
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
 * @property int $customer_id
 * @property string $license_plate
 * @property string $brand
 * @property string $model
 * @property int|null $year
 * @property string|null $color
 * @property string|null $chassis_number
 * @property string|null $engine_number
 * @property int $latest_mileage
 * @property string|null $notes
 * @property-read Customer $customer
 */
#[Fillable(['customer_id', 'license_plate', 'brand', 'model', 'year', 'color', 'chassis_number', 'engine_number', 'latest_mileage', 'notes'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['year' => 'integer', 'latest_mileage' => 'integer'];
    }

    public function setLicensePlateAttribute(string $value): void
    {
        $this->attributes['license_plate'] = WorkshopInput::plate($value);
    }

    /** @param Builder<Vehicle> $query */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $term = Str::squish($term);
        if ($term === '') {
            return;
        }

        $text = WorkshopInput::like(Str::lower($term));
        $query->where(function (Builder $query) use ($term, $text) {
            $query->whereRaw("license_plate LIKE ? ESCAPE '!'", [WorkshopInput::like(WorkshopInput::plate($term))])
                ->orWhereRaw("LOWER(brand) LIKE ? ESCAPE '!'", [$text])
                ->orWhereRaw("LOWER(model) LIKE ? ESCAPE '!'", [$text])
                ->orWhereHas('customer', fn (Builder $customers) => $customers
                    ->where(fn (Builder $customer) => $customer
                        ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$text])
                        ->orWhereRaw("phone LIKE ? ESCAPE '!'", [WorkshopInput::like(WorkshopInput::phone($term))])));
        });
    }

    /** @return HasMany<ServiceOrder, $this> */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }
}
