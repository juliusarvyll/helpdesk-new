<?php

namespace App\Filament\Resources\PreventiveMaintenanceSessions\Pages;

use App\Filament\Resources\PreventiveMaintenanceSessions\PreventiveMaintenanceSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPreventiveMaintenanceSession extends EditRecord
{
    protected static string $resource = PreventiveMaintenanceSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
