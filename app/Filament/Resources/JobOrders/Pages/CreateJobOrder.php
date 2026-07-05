<?php

namespace App\Filament\Resources\JobOrders\Pages;

use App\Filament\Resources\JobOrders\JobOrderResource;
use App\JobOrderCreationService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateJobOrder extends CreateRecord
{
    protected static string $resource = JobOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(JobOrderCreationService::class)->create([
            ...$data,
            'department_id' => Filament::getTenant()?->id,
            'source' => $data['source'] ?? 'manual',
        ], auth()->user());
    }
}
