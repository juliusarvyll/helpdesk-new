<?php

namespace App\Notifications;

use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\TicketResource;
use App\Models\PreventiveMaintenanceSchedule;
use App\Models\Ticket;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class PreventiveMaintenanceWorkGenerated extends Notification
{
    use Queueable;

    public function __construct(public PreventiveMaintenanceSchedule $schedule, public Model $work) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $isTicket = $this->work instanceof Ticket;
        $url = $isTicket
            ? TicketResource::getUrl('view', ['tenant' => $this->schedule->department, 'record' => $this->work])
            : JobOrderResource::getUrl('view', ['tenant' => $this->schedule->department, 'record' => $this->work]);

        return FilamentNotification::make()
            ->title('Preventive Maintenance Work Generated')
            ->body(($isTicket ? 'Ticket' : 'Job Order')." #{$this->work->getKey()}: {$this->schedule->title}")
            ->icon('heroicon-o-calendar-days')
            ->iconColor('info')
            ->actions([Action::make('view')->label('View Work')->url($url)])
            ->getDatabaseMessage();
    }
}
