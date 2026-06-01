<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_deleted' => false,
        ];
    }
}
