<?php

namespace App\Filament\Resources\PreventiveMaintenanceSchedules\Pages;

use App\Filament\Resources\PreventiveMaintenanceSchedules\PreventiveMaintenanceScheduleResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreatePreventiveMaintenanceSchedule extends CreateRecord
{
    protected static string $resource = PreventiveMaintenanceScheduleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['department_id'] = Filament::getTenant()?->id;
        $data['created_by'] = auth()->id();
        $data['next_due_at'] ??= $data['starts_at'];

        return $data;
    }
}
