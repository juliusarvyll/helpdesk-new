<?php

namespace App\Observers;

use App\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;
use App\Notifications\JobOrderAssigned;
use App\Notifications\NewJobOrderCreated;
use App\PreventiveMaintenanceLogStatus;

class JobOrderObserver
{
    public function created(JobOrder $jobOrder): void
    {
        User::role(['super_admin', 'admin', 'job_order_manager', 'maintenance_staff'])
            ->canAccessDepartment($jobOrder->department_id)
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->get()
            ->each->notify(new NewJobOrderCreated($jobOrder));
    }

    public function updated(JobOrder $jobOrder): void
    {
        if ($jobOrder->wasChanged('assigned_to_user_id') && $jobOrder->assignedUser) {
            $jobOrder->assignedUser->notify(new JobOrderAssigned($jobOrder));
        }

        if ($jobOrder->wasChanged('status') && $jobOrder->status === JobOrderStatus::Closed) {
            $jobOrder->preventiveMaintenanceLogs()
                ->where('status', PreventiveMaintenanceLogStatus::Generated->value)
                ->update(['status' => PreventiveMaintenanceLogStatus::Completed->value, 'completed_at' => now()]);
        }
    }
}
