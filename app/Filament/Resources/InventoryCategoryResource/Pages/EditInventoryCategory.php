<?php

namespace App\Filament\Resources\InventoryCategoryResource\Pages;

use App\Filament\Resources\InventoryCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInventoryCategory extends EditRecord
{
    protected static string $resource = InventoryCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('delete')
                ->requiresConfirmation()
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->visible(fn (): bool => auth()->user()?->can('delete_inventory::category') ?? false)
                ->action(function (): void {
                    $this->record->update(['is_deleted' => true]);

                    $this->redirect(InventoryCategoryResource::getUrl('index'));
                }),
        ];
    }
}
