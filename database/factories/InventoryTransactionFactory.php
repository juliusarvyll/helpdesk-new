<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryTransaction>
 */
class InventoryTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'ticket_id' => null,
            'user_id' => User::factory(),
            'assigned_to_user_id' => null,
            'type' => 'created',
            'quantity' => 1,
            'from_status' => null,
            'to_status' => 'available',
            'notes' => fake()->optional()->sentence(),
            'metadata' => null,
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'assigned',
            'from_status' => 'available',
            'to_status' => 'assigned',
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'returned',
            'from_status' => 'assigned',
            'to_status' => 'available',
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'consumed',
            'quantity' => fake()->numberBetween(1, 10),
        ]);
    }
}
