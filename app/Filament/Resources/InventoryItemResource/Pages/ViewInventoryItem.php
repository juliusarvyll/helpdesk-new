<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InventoryItemResource\Pages\Concerns\HasInventoryItemActions;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryItem extends ViewRecord
{
    use HasInventoryItemActions;

    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->inventoryItemActions(),
            Actions\EditAction::make(),
        ];
    }
}
