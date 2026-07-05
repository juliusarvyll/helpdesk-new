<?php

namespace App\Filament\Resources\PmsChecklistTemplates\Pages;

use App\Filament\Resources\PmsChecklistTemplates\PmsChecklistTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPmsChecklistTemplate extends EditRecord
{
    protected static string $resource = PmsChecklistTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
