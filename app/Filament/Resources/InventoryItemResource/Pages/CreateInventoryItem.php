<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryTransaction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryItem extends CreateRecord
{
    protected static string $resource = InventoryItemResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['department_id'] = Filament::getTenant()?->id;

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! auth()->user()) {
            return;
        }

        InventoryTransaction::create([
            'inventory_item_id' => $this->record->id,
            'ticket_id' => null,
            'user_id' => auth()->id(),
            'assigned_to_user_id' => null,
            'type' => 'created',
            'quantity' => $this->record->quantity,
            'from_status' => null,
            'to_status' => $this->record->status,
            'notes' => null,
            'metadata' => null,
        ]);
    }
}
