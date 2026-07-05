<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\InventoryItem;
use App\Models\PreventiveMaintenanceSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreventiveMaintenanceScheduleFactory extends Factory
{
    protected $model = PreventiveMaintenanceSchedule::class;

    public function definition(): array
    {
        return ['department_id' => Department::factory(), 'inventory_item_id' => InventoryItem::factory(), 'title' => fake()->sentence(), 'description' => fake()->paragraph(), 'frequency' => 'monthly', 'starts_at' => now(), 'next_due_at' => now(), 'is_active' => true, 'created_by' => User::factory()];
    }
}
