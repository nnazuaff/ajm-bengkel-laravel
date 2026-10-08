<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['receipt_id' => Receipt::factory()->state(['status' => 'final', 'grand_total' => '100.00']), 'amount' => '100.00', 'method' => 'cash', 'paid_at' => now(), 'created_by' => User::factory()->state(['role' => 'admin'])];
    }
}
