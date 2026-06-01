<?php

namespace App\Filament\Resources\IssueListResource\Pages;

use App\Filament\Resources\IssueListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIssueLists extends ListRecords
{
    protected static string $resource = IssueListResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
