<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Location;
use App\Models\PreventiveMaintenanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreventiveMaintenanceSessionFactory extends Factory
{
    protected $model = PreventiveMaintenanceSession::class;

    public function definition(): array
    {
        return ['department_id' => Department::factory(), 'location_id' => Location::factory(), 'started_by' => User::factory(), 'started_at' => now(), 'status' => 'active'];
    }
}
