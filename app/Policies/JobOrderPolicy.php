<?php

namespace App\Policies;

use App\Models\JobOrder;
use App\Models\User;

class JobOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_job_order');
    }

    public function view(User $user, JobOrder $jobOrder): bool
    {
        return $user->can('view_job_order') && $this->canAccessDepartment($user, $jobOrder);
    }

    public function create(User $user): bool
    {
        return $user->can('create_job_order');
    }

    public function update(User $user, JobOrder $jobOrder): bool
    {
        return $user->can('update_job_order') && $this->canAccessDepartment($user, $jobOrder);
    }

    public function delete(User $user, JobOrder $jobOrder): bool
    {
        return $user->can('delete_job_order') && $this->canAccessDepartment($user, $jobOrder);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_job_order');
    }

    public function assign(User $user, JobOrder $jobOrder): bool
    {
        return $user->can('assign_job_order') && $this->canAccessDepartment($user, $jobOrder);
    }

    public function close(User $user, JobOrder $jobOrder): bool
    {
        return $user->can('close_job_order') && $this->canAccessDepartment($user, $jobOrder);
    }

    private function canAccessDepartment(User $user, JobOrder $jobOrder): bool
    {
        return User::query()->whereKey($user->getKey())->canAccessDepartment($jobOrder->department_id)->exists();
    }
}
