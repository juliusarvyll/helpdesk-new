<?php

namespace App\Policies;

use App\Models\PreventiveMaintenanceLog;
use App\Models\User;

class PreventiveMaintenanceLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'technical_support']);
    }

    public function view(User $user, PreventiveMaintenanceLog $log): bool
    {
        return $this->viewAny($user) && User::query()->whereKey($user->getKey())->canAccessDepartment($log->schedule->department_id)->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PreventiveMaintenanceLog $log): bool
    {
        return false;
    }

    public function delete(User $user, PreventiveMaintenanceLog $log): bool
    {
        return false;
    }
}
