<?php

namespace App\Filament\Resources\PreventiveMaintenanceSchedules\Pages;

use App\Filament\Resources\PreventiveMaintenanceSchedules\PreventiveMaintenanceScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPreventiveMaintenanceSchedules extends ListRecords
{
    protected static string $resource = PreventiveMaintenanceScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
