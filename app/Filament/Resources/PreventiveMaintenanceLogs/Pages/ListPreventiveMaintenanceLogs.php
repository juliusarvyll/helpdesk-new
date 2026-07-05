<?php

namespace App\Filament\Resources\PreventiveMaintenanceLogs\Pages;

use App\Filament\Resources\PreventiveMaintenanceLogs\PreventiveMaintenanceLogResource;
use Filament\Resources\Pages\ListRecords;

class ListPreventiveMaintenanceLogs extends ListRecords
{
    protected static string $resource = PreventiveMaintenanceLogResource::class;
}
