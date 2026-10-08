<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\ServiceItem;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceItem> */
class ServiceItemFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['service_order_id' => ServiceOrder::factory(), 'inventory_item_id' => InventoryItem::factory(), 'description' => 'Busi', 'quantity' => 1, 'unit_price' => '15000.00', 'subtotal' => '15000.00', 'used_by' => User::factory()->state(['role' => 'admin'])];
    }
}
