<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\TicketStatus;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public TicketStatus $previousStatus,
        public TicketStatus $newStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Ticket Status Updated')
            ->body("Ticket #{$this->ticket->id} changed from {$this->previousStatus->label()} to {$this->newStatus->label()}.")
            ->icon('heroicon-o-arrow-path')
            ->iconColor($this->newStatus->color())
            ->actions([
                Action::make('view')
                    ->label('View Ticket')
                    ->url(route('filament.admin.resources.tickets.view', ['tenant' => $this->ticket->department, 'record' => $this->ticket->id])),
            ])
            ->getDatabaseMessage();
    }
}
