<?php

namespace Database\Factories;

use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ServiceOrder> */
class ServiceOrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'service_number' => 'SRV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'vehicle_id' => Vehicle::factory(),
            'customer_id' => fn (array $attributes) => Vehicle::query()->whereKey($attributes['vehicle_id'])->firstOrFail()->customer_id,
            'received_by' => User::factory()->state(['role' => 'admin']),
            'source' => 'walk_in',
            'current_mileage' => 1000,
            'complaint' => 'Rem belakang berbunyi.',
            'status' => 'waiting',
            'received_at' => now(),
        ];
    }
}
