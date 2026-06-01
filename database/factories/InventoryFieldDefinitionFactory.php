<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use App\Models\InventoryFieldDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryFieldDefinition>
 */
class InventoryFieldDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'inventory_category_id' => InventoryCategory::factory(),
            'key' => fake()->unique()->word(),
            'label' => fake()->words(2, true),
            'type' => fake()->randomElement(['text', 'number', 'date', 'boolean', 'select']),
            'options' => null,
            'is_required' => fake()->boolean(30),
            'sort_order' => 0,
        ];
    }

    public function select(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'select',
            'options' => fake()->words(5),
        ]);
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }
}
