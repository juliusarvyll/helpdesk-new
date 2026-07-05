<?php

namespace Database\Factories;

use App\Models\PreventiveMaintenanceLog;
use App\Models\PreventiveMaintenanceSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreventiveMaintenanceLogFactory extends Factory
{
    protected $model = PreventiveMaintenanceLog::class;

    public function definition(): array
    {
        return ['schedule_id' => PreventiveMaintenanceSchedule::factory(), 'generated_at' => now(), 'status' => 'generated'];
    }
}
