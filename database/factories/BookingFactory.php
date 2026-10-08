<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_number' => 'BKG-'.fake()->unique()->numerify('########-###'),
            'submitted_by' => User::factory(), 'name' => fake()->name(),
            'phone' => fake()->numerify('628##########'), 'email' => fake()->safeEmail(),
            'license_plate' => fake()->unique()->bothify('B####???'),
            'brand' => 'Honda', 'model' => 'Vario', 'year' => 2023, 'current_mileage' => 1500,
            'booking_date' => now()->addDay()->toDateString(), 'arrival_time' => '09:00',
            'service_type' => 'Servis berkala', 'complaint' => 'Rem berisik',
            'notes' => null, 'admin_notes' => null, 'status' => BookingStatus::Pending,
        ];
    }
}
