<?php

namespace Database\Factories;

use App\Models\PmsChecklistTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PmsChecklistTemplateFactory extends Factory
{
    protected $model = PmsChecklistTemplate::class;

    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'description' => fake()->sentence(), 'is_active' => true, 'created_by' => User::factory()];
    }
}
