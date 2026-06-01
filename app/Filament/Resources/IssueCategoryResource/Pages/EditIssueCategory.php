<?php

namespace App\Filament\Resources\IssueCategoryResource\Pages;

use App\Filament\Resources\IssueCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIssueCategory extends EditRecord
{
    protected static string $resource = IssueCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
