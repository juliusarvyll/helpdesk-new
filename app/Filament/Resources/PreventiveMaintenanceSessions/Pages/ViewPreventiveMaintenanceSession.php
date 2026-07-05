<?php

namespace App\Filament\Resources\PreventiveMaintenanceSessions\Pages;

use App\Filament\Resources\PreventiveMaintenanceSessions\PreventiveMaintenanceSessionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPreventiveMaintenanceSession extends ViewRecord
{
    protected static string $resource = PreventiveMaintenanceSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
