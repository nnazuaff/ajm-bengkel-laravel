<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '628'.fake()->unique()->numerify('##########'),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'notes' => null,
        ];
    }
}
