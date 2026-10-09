<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\User;
use App\Support\WorkshopInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    /** @param array<string, mixed> $input */
    public function create(User $actor, array $input): Booking
    {
        Gate::forUser($actor)->authorize('create', Booking::class);

        return $this->store($actor, $input);
    }

    /** @param array<string, mixed> $input */
    public function guest(array $input): Booking
    {
        $key = 'guest-booking:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['throttle' => 'Terlalu banyak permintaan. Coba lagi dalam satu menit.']);
        }
        RateLimiter::hit($key, 60);

        return $this->store(null, $input);
    }

    /** @param array<string, mixed> $input */
    private function store(?User $actor, array $input): Booking
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value) === '' ? null : trim($value);
            }
        }
        if (is_string($input['phone'] ?? null)) {
            $input['phone'] = WorkshopInput::phone($input['phone']);
        }
        if (is_string($input['license_plate'] ?? null)) {
            $input['license_plate'] = WorkshopInput::plate($input['license_plate']);
        }
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^62[1-9][0-9]{7,12}$/D'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:254'],
            'license_plate' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/D'],
            'brand' => ['required', 'string', 'max:60'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'current_mileage' => ['required', 'integer', 'between:0,2147483647'],
            ...self::slotRules(),
            'service_type' => ['required', 'string', 'max:100'],
            'complaint' => ['required', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $data): Booking {
            $currentActor = $actor !== null ? User::findOrFail($actor->id) : null;
            if ($currentActor !== null) {
                Gate::forUser($currentActor)->authorize('create', Booking::class);
            }
            $customer = $currentActor?->role === Role::Customer
                ? app(CustomerResolver::class)->forUser($currentActor, $data)
                : app(CustomerResolver::class)->resolve($data);
            if ($currentActor?->role === Role::Customer && $customer->user_id === $currentActor->id) {
                $data = array_replace($data, $customer->only(['name', 'phone', 'email']));
            }
            $vehicle = app(VehicleResolver::class)->resolve($customer, $data);
            // Serialize slot checks on the resolved vehicle, including concurrent submissions.
            $vehicle = $vehicle->newQuery()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            if (Booking::query()->where('vehicle_id', $vehicle->id)
                ->whereDate('booking_date', $data['booking_date'])
                ->whereIn('arrival_time', [$data['arrival_time'], $data['arrival_time'].':00'])
                ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::Rescheduled, BookingStatus::Arrived])->exists()) {
                throw ValidationException::withMessages(['arrival_time' => 'Permintaan booking untuk motor dan jadwal ini sudah masuk. Silakan pilih jadwal lain.']);
            }
            $booking = Booking::create([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                ...$data,
                'booking_number' => app(NextServiceNumber::class)->generate('BKG'),
                'submitted_by' => $currentActor?->role === Role::Customer ? $currentActor->id : null,
                'status' => BookingStatus::Pending,
            ]);
            $audit = AuditLog::create([
                'actor_id' => $actor?->id, 'action' => 'booking.created',
                'entity_type' => Booking::class, 'entity_id' => $booking->id,
                'context' => ['status' => BookingStatus::Pending->value],
            ]);

            if (! $audit->exists) {
                throw new \RuntimeException('Audit gagal disimpan.');
            }

            return $booking;
        }, attempts: 5);
    }

    /** @return array<string, list<string>> */
    public static function slotRules(): array
    {
        return [
            'booking_date' => ['required', 'string', 'date_format:Y-m-d', 'after_or_equal:'.now()->toDateString(), 'before_or_equal:'.now()->addDays(90)->toDateString()],
            'arrival_time' => ['required', 'string', 'date_format:H:i', 'after_or_equal:08:00', 'before_or_equal:17:00'],
        ];
    }
}
