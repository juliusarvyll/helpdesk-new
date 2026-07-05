<?php

namespace App\Policies;

use App\Models\PreventiveMaintenanceSchedule;
use App\Models\User;

class PreventiveMaintenanceSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'technical_support']);
    }

    public function view(User $user, PreventiveMaintenanceSchedule $schedule): bool
    {
        return $this->viewAny($user) && User::query()->whereKey($user->getKey())->canAccessDepartment($schedule->department_id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PreventiveMaintenanceSchedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }

    public function delete(User $user, PreventiveMaintenanceSchedule $schedule): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']) && $this->view($user, $schedule);
    }
}
