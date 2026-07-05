<?php

namespace App\Filament\Resources\PreventiveMaintenanceSessions\Pages;

use App\Filament\Resources\PreventiveMaintenanceSessions\PreventiveMaintenanceSessionResource;
use App\Models\Location;
use App\Models\PmsChecklistTemplate;
use App\PmsInspectionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePreventiveMaintenanceSession extends CreateRecord
{
    protected static string $resource = PreventiveMaintenanceSessionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PmsInspectionService::class)->startSession(
            Location::query()->findOrFail($data['location_id']),
            auth()->user(),
            PmsChecklistTemplate::query()->findOrFail($data['checklist_template_id']),
            $data['remarks'] ?? null,
        );
    }
}
