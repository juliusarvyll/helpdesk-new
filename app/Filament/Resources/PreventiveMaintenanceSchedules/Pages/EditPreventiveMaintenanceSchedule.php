<?php

namespace App\Filament\Resources\PreventiveMaintenanceSchedules\Pages;

use App\Filament\Resources\PreventiveMaintenanceSchedules\PreventiveMaintenanceScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPreventiveMaintenanceSchedule extends EditRecord
{
    protected static string $resource = PreventiveMaintenanceScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
