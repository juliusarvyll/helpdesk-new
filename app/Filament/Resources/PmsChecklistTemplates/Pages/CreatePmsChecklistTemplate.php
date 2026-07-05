<?php

namespace App\Filament\Resources\PmsChecklistTemplates\Pages;

use App\Filament\Resources\PmsChecklistTemplates\PmsChecklistTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePmsChecklistTemplate extends CreateRecord
{
    protected static string $resource = PmsChecklistTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
