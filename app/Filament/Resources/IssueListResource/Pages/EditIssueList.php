<?php

namespace App\Filament\Resources\IssueListResource\Pages;

use App\Filament\Resources\IssueListResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIssueList extends EditRecord
{
    protected static string $resource = IssueListResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
