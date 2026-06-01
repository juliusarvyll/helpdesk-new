<?php

namespace App\Filament\Resources\InventoryCategoryResource\Pages;

use App\Filament\Resources\InventoryCategoryResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryCategory extends CreateRecord
{
    protected static string $resource = InventoryCategoryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['department_id'] = Filament::getTenant()?->id;

        return $data;
    }
}
