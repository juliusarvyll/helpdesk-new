<?php

namespace App;

use App\Models\JobOrder;
use App\Models\PreventiveMaintenanceLog;
use App\Models\PreventiveMaintenanceSchedule;
use App\Models\Ticket;
use App\Notifications\PreventiveMaintenanceWorkGenerated;
use Illuminate\Support\Facades\DB;

class PreventiveMaintenanceGenerationService
{
    public function __construct(public AssetWorkOrderCreationService $workOrders) {}

    public function generate(PreventiveMaintenanceSchedule $schedule, bool $force = false): PreventiveMaintenanceLog
    {
        return DB::transaction(function () use ($schedule, $force): PreventiveMaintenanceLog {
            $lockedSchedule = PreventiveMaintenanceSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $lockedSchedule->load(['inventoryItem', 'inventoryItemSerialNumber', 'creator', 'assignedUser']);

            if (! $lockedSchedule->is_active || (! $force && $lockedSchedule->next_due_at->isFuture())) {
                return $lockedSchedule->logs()->create([
                    'generated_at' => now(),
                    'status' => PreventiveMaintenanceLogStatus::Skipped,
                    'remarks' => 'The schedule is inactive or not yet due.',
                ]);
            }

            if ($this->hasOpenGeneratedWork($lockedSchedule)) {
                $log = $lockedSchedule->logs()->create([
                    'generated_at' => now(),
                    'status' => PreventiveMaintenanceLogStatus::Skipped,
                    'remarks' => 'Open preventive-maintenance work already exists.',
                ]);
                $this->advance($lockedSchedule);

                return $log;
            }

            $creator = $lockedSchedule->creator;
            $work = $this->workOrders->create($lockedSchedule->inventoryItem, [
                'subject' => $lockedSchedule->title,
                'description' => $lockedSchedule->description,
                'priority' => 'normal',
                'client_id' => $creator->id,
                'department_id' => $lockedSchedule->department_id,
                'inventory_item_serial_number_id' => $lockedSchedule->inventory_item_serial_number_id,
                'source' => 'preventive_maintenance',
                'assigned_to_user_id' => $lockedSchedule->assigned_to_user_id,
            ], $creator);

            if ($work instanceof Ticket && $lockedSchedule->assignedUser?->hasRole('technical_support')) {
                $work->technicalSupportUsers()->syncWithoutDetaching([$lockedSchedule->assigned_to_user_id]);
                $work->syncAssignmentState();
            }

            $log = $lockedSchedule->logs()->create([
                'ticket_id' => $work instanceof Ticket ? $work->id : null,
                'job_order_id' => $work instanceof JobOrder ? $work->id : null,
                'generated_at' => now(),
                'status' => PreventiveMaintenanceLogStatus::Generated,
            ]);

            $this->advance($lockedSchedule);

            if ($lockedSchedule->assignedUser) {
                $lockedSchedule->assignedUser->notify(new PreventiveMaintenanceWorkGenerated($lockedSchedule, $work));
            }

            return $log;
        });
    }

    private function hasOpenGeneratedWork(PreventiveMaintenanceSchedule $schedule): bool
    {
        return $schedule->logs()
            ->where('status', PreventiveMaintenanceLogStatus::Generated->value)
            ->where(function ($query): void {
                $query->whereHas('ticket', fn ($query) => $query->where('status', '!=', TicketStatus::Closed->value))
                    ->orWhereHas('jobOrder', fn ($query) => $query->open());
            })
            ->exists();
    }

    private function advance(PreventiveMaintenanceSchedule $schedule): void
    {
        $schedule->forceFill([
            'last_generated_at' => now(),
            'next_due_at' => $schedule->nextDueAfterGeneration(),
        ])->save();
    }
}
