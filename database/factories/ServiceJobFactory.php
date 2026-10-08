<?php

namespace Database\Factories;

use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceJob> */
class ServiceJobFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'service_order_id' => ServiceOrder::factory(),
            'name' => 'Bersihkan rem',
            'description' => 'Bersihkan tromol.',
            'labor_price' => '25000.00',
            'status' => 'pending',
        ];
    }
}
