<?php

namespace App\Observers;

use App\Models\Ticket;
use App\PreventiveMaintenanceLogStatus;
use App\TicketStatus;

class TicketObserver
{
    public function updated(Ticket $ticket): void
    {
        if (! $ticket->wasChanged('status') || $ticket->status !== TicketStatus::Closed) {
            return;
        }

        $ticket->preventiveMaintenanceLogs()
            ->where('status', PreventiveMaintenanceLogStatus::Generated->value)
            ->update(['status' => PreventiveMaintenanceLogStatus::Completed->value, 'completed_at' => now()]);
    }
}
