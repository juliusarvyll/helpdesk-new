<?php

namespace App\Notifications;

use App\Models\Ticket;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketAssigned extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Ticket Assigned to You')
            ->body("Ticket #{$this->ticket->id}: {$this->ticket->subject}")
            ->icon('heroicon-o-user-circle')
            ->iconColor('info')
            ->actions([
                Action::make('view')
                    ->label('View Ticket')
                    ->url(route('filament.admin.resources.tickets.view', ['tenant' => $this->ticket->department, 'record' => $this->ticket->id])),
            ])
            ->getDatabaseMessage();
    }
}
