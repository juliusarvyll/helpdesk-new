<?php

namespace App\Filament\Resources\IssueCategoryResource\Pages;

use App\Filament\Resources\IssueCategoryResource;
use App\Filament\Resources\IssueCategoryResource\RelationManagers\IssueListRelationManager;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewIssueCategory extends ViewRecord
{
    protected static string $resource = IssueCategoryResource::class;

    protected function getAllRelationManagers(): array
    {
        return [
            IssueListRelationManager::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
