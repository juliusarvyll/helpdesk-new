<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InventoryItemResource\Pages\Concerns\HasInventoryItemActions;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInventoryItem extends EditRecord
{
    use HasInventoryItemActions;

    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->inventoryItemActions(),
            Actions\Action::make('delete')
                ->requiresConfirmation()
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->record) ?? false)
                ->action(function (): void {
                    $this->record->update(['is_deleted' => true]);

                    $this->redirect(InventoryItemResource::getUrl('index'));
                }),
        ];
    }
}
