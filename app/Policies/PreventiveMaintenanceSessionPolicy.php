<?php

namespace App\Policies;

use App\Models\PreventiveMaintenanceSession;
use App\Models\User;

class PreventiveMaintenanceSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'technical_support']);
    }

    public function view(User $user, PreventiveMaintenanceSession $session): bool
    {
        return $this->viewAny($user) && User::query()->whereKey($user->getKey())->canAccessDepartment($session->department_id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PreventiveMaintenanceSession $session): bool
    {
        return $this->view($user, $session);
    }

    public function delete(User $user, PreventiveMaintenanceSession $session): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']) && $this->view($user, $session);
    }
}
