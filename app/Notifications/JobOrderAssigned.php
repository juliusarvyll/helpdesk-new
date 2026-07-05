<?php

namespace App\Notifications;

use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Models\JobOrder;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class JobOrderAssigned extends Notification
{
    use Queueable;

    public function __construct(public JobOrder $jobOrder) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Job Order Assigned to You')
            ->body("Job Order #{$this->jobOrder->id}: {$this->jobOrder->subject}")
            ->icon('heroicon-o-user-circle')
            ->iconColor('info')
            ->actions([Action::make('view')->label('View Job Order')->url(JobOrderResource::getUrl('view', ['tenant' => $this->jobOrder->department, 'record' => $this->jobOrder]))])
            ->getDatabaseMessage();
    }
}
