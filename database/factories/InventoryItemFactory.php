<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryItem> */
class InventoryItemFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['sku' => fake()->unique()->bothify('SP-????-####'), 'name' => fake()->words(3, true), 'purchase_price' => '10000.00', 'selling_price' => '15000.00', 'current_stock' => 0, 'minimum_stock' => 2, 'unit' => 'pcs', 'is_active' => true];
    }
}
