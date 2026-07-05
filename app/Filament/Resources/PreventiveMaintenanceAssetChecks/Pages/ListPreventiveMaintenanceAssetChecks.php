<?php

namespace App\Filament\Resources\PreventiveMaintenanceAssetChecks\Pages;

use App\Filament\Resources\PreventiveMaintenanceAssetChecks\PreventiveMaintenanceAssetCheckResource;
use Filament\Resources\Pages\ListRecords;

class ListPreventiveMaintenanceAssetChecks extends ListRecords
{
    protected static string $resource = PreventiveMaintenanceAssetCheckResource::class;
}
