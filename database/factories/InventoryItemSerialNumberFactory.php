<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemSerialNumberFactory extends Factory
{
    protected $model = InventoryItemSerialNumber::class;

    public function definition(): array
    {
        return ['inventory_item_id' => InventoryItem::factory(), 'serial_number' => fake()->unique()->bothify('SN-########'), 'status' => 'available'];
    }
}
