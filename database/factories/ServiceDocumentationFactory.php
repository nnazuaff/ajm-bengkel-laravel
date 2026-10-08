<?php

namespace Database\Factories;

use App\Models\ServiceDocumentation;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceDocumentation> */
class ServiceDocumentationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'service_order_id' => ServiceOrder::factory(), 'service_job_id' => null,
            'disk' => 'local',
            'path' => fn (array $attributes) => 'service-documentation/'.$attributes['service_order_id'].'/'.bin2hex(random_bytes(16)).'.jpg',
            'category' => 'evidence', 'caption' => null,
            'uploaded_by' => User::factory()->state(['role' => 'admin']),
        ];
    }
}
