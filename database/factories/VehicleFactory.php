<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'license_plate' => 'B'.fake()->unique()->numerify('#####').'AJM',
            'brand' => 'Honda',
            'model' => 'Vario 125',
            'year' => fake()->numberBetween(2010, now()->year),
            'color' => null,
            'chassis_number' => null,
            'engine_number' => null,
            'latest_mileage' => fake()->numberBetween(0, 100000),
            'notes' => null,
        ];
    }
}
