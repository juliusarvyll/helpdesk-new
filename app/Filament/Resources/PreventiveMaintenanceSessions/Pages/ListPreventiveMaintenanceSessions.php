<?php

namespace App\Filament\Resources\PreventiveMaintenanceSessions\Pages;

use App\Filament\Resources\PreventiveMaintenanceSessions\PreventiveMaintenanceSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPreventiveMaintenanceSessions extends ListRecords
{
    protected static string $resource = PreventiveMaintenanceSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
