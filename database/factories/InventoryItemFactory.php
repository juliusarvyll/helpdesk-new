<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'inventory_category_id' => InventoryCategory::factory(),
            'asset_tag' => fake()->optional()->bothify('AST-####'),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'status' => 'available',
            'quantity' => 1,
            'unit' => null,
            'location_id' => null,
            'assigned_to_user_id' => null,
            'department_id' => null,
            'current_ticket_id' => null,
            'metadata' => null,
            'purchased_at' => fake()->optional()->dateTimeBetween('-2 years'),
            'warranty_expires_at' => fake()->optional()->dateTimeBetween('now', '+3 years'),
            'is_deleted' => false,
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'assigned',
        ]);
    }

    public function consumable(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => fake()->numberBetween(10, 100),
            'unit' => fake()->randomElement(['pcs', 'box', 'pack', 'unit']),
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_deleted' => true,
        ]);
    }
}
