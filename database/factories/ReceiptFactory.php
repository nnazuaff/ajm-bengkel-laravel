<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Receipt> */
class ReceiptFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['public_id' => (string) Str::uuid(), 'receipt_number' => 'BON-'.Str::upper(Str::random(12)), 'cashier_id' => User::factory()->state(['role' => 'admin']), 'transaction_date' => now(), 'status' => 'draft', 'payment_status' => 'unpaid', 'subtotal' => '0.00', 'discount' => '0.00', 'grand_total' => '0.00', 'workshop_snapshot' => ['name' => 'AJM Bengkel'], 'customer_snapshot' => ['name' => 'Umum']];
    }
}
