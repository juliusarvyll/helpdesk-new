<?php

namespace Database\Factories;

use App\Models\IssueCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class IssueListFactory extends Factory
{
    public function definition(): array
    {
        return [
            'issue_category_id' => IssueCategory::factory(),
            'issue' => fake()->sentence(),
            'is_deleted' => 0,
        ];
    }
}
