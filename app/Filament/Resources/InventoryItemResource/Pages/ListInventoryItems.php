<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Jobs\ImportInventoryItemsFromCsv;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('file')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', 'application/csv'])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $possiblePaths = [
                        storage_path('app/public/' . $data['file']),
                        storage_path('app/' . $data['file']),
                        storage_path('app/private/' . $data['file']),
                        public_path('storage/' . $data['file']),
                    ];

                    $filePath = null;
                    foreach ($possiblePaths as $path) {
                        if (file_exists($path)) {
                            $filePath = $path;
                            break;
                        }
                    }

                    if (! $filePath) {
                        Notification::make()
                            ->danger()
                            ->title('File not found')
                            ->body('Tried: ' . implode(', ', $possiblePaths))
                            ->send();

                        return;
                    }

                    $csv = array_map('str_getcsv', file($filePath));
                    $header = array_shift($csv);

                    $tenant = Filament::getTenant();
                    $actorId = auth()->id();
                    $rows = [];

                    if (! $actorId) {
                        Notification::make()
                            ->danger()
                            ->title('Import failed')
                            ->body('You must be signed in to import inventory items.')
                            ->send();

                        return;
                    }

                    foreach ($csv as $row) {
                        if (empty(array_filter($row))) {
                            continue;
                        }

                        $rows[] = array_combine($header, $row);
                    }

                    if ($rows === []) {
                        Notification::make()
                            ->warning()
                            ->title('No items found')
                            ->body('The CSV did not contain any importable rows.')
                            ->send();

                        return;
                    }

                    ImportInventoryItemsFromCsv::dispatch(
                        $rows,
                        $tenant?->id,
                        $actorId,
                    );

                    Notification::make()
                        ->success()
                        ->title('Import queued')
                        ->body(count($rows) . ' items will be imported in the background.')
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
