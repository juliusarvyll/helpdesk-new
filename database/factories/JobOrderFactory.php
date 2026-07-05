<?php

namespace Database\Factories;

use App\JobOrderStatus;
use App\Models\Department;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobOrderFactory extends Factory
{
    protected $model = JobOrder::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'client_id' => User::factory(),
            'created_by' => User::factory(),
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'critical']),
            'status' => JobOrderStatus::Active,
            'source' => 'manual',
            'requested_by_name' => fake()->name(),
        ];
    }
}
