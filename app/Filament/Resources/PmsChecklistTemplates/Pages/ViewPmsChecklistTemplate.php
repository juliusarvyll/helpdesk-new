<?php

namespace App\Filament\Resources\PmsChecklistTemplates\Pages;

use App\Filament\Resources\PmsChecklistTemplates\PmsChecklistTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPmsChecklistTemplate extends ViewRecord
{
    protected static string $resource = PmsChecklistTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
