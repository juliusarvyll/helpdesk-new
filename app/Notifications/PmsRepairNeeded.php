<?php

namespace App\Notifications;

use App\Filament\Resources\PreventiveMaintenanceSessions\PreventiveMaintenanceSessionResource;
use App\Models\PreventiveMaintenanceAssetCheck;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PmsRepairNeeded extends Notification
{
    use Queueable;

    public function __construct(public PreventiveMaintenanceAssetCheck $assetCheck) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->assetCheck->loadMissing(['session.department', 'serialNumber', 'inventoryItem']);

        return FilamentNotification::make()
            ->title('PMS Asset Needs Repair')
            ->body("{$this->assetCheck->inventoryItem->name} ({$this->assetCheck->serialNumber->serial_number}) requires repair.")
            ->icon('heroicon-o-wrench-screwdriver')
            ->iconColor('danger')
            ->actions([Action::make('view')->label('View PMS Session')->url(PreventiveMaintenanceSessionResource::getUrl('view', ['tenant' => $this->assetCheck->session->department, 'record' => $this->assetCheck->session]))])
            ->getDatabaseMessage();
    }
}
