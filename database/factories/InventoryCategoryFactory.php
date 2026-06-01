<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCategory>
 */
class InventoryCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department_id' => null,
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['asset', 'consumable', 'license', 'peripheral', 'spare_part']),
            'parent_id' => null,
            'is_deleted' => false,
        ];
    }

    public function asset(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'asset',
        ]);
    }

    public function consumable(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'consumable',
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_deleted' => true,
        ]);
    }
}
