<?php

namespace App\Policies;

use App\Models\PreventiveMaintenanceAssetCheck;
use App\Models\User;

class PreventiveMaintenanceAssetCheckPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'technical_support']);
    }

    public function view(User $user, PreventiveMaintenanceAssetCheck $check): bool
    {
        return $this->viewAny($user) && User::query()->whereKey($user->getKey())->canAccessDepartment($check->session->department_id)->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PreventiveMaintenanceAssetCheck $check): bool
    {
        return false;
    }

    public function delete(User $user, PreventiveMaintenanceAssetCheck $check): bool
    {
        return false;
    }
}
