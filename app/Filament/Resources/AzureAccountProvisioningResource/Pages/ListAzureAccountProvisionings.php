<?php

namespace App\Filament\Resources\AzureAccountProvisioningResource\Pages;

use App\Filament\Resources\AzureAccountProvisioningResource;
use App\Jobs\ImportMicrosoftUsers;
use App\MicrosoftGraphService;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAzureAccountProvisionings extends ListRecords
{
    protected static string $resource = AzureAccountProvisioningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importMicrosoftUsers')
                ->label('Import Microsoft Users')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->modalHeading('Import Microsoft users')
                ->modalDescription('This imports users with Microsoft 365 A3 Student Use Benefit or A3 Faculty licenses from Microsoft Graph into this resource.')
                ->form([
                    CheckboxList::make('columns')
                        ->label('Columns to import')
                        ->options(MicrosoftGraphService::importableColumns())
                        ->default(MicrosoftGraphService::defaultImportColumns())
                        ->columns(2)
                        ->required()
                        ->bulkToggleable(),
                ])
                ->action(function (array $data): void {
                    dispatch(new ImportMicrosoftUsers($data['columns'] ?? [], auth()->id()));

                    Notification::make()
                        ->title('Microsoft users import queued')
                        ->body('The import is running in the background. You will receive a notification when it finishes.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
