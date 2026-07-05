<?php

namespace App\Filament\Resources\PmsChecklistTemplates\Pages;

use App\Filament\Resources\PmsChecklistTemplates\PmsChecklistTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPmsChecklistTemplates extends ListRecords
{
    protected static string $resource = PmsChecklistTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
