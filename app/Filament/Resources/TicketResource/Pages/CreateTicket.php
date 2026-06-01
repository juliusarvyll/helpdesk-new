<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Models\User;
use App\Notifications\NewTicketCreated;
use Filament\Resources\Pages\CreateRecord;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['department']);

        $data = TicketResource::sanitizeTechnicalSupportAssignmentData($data, allowAssignment: false);
        $data = TicketResource::assignCreatorClientAndDepartmentData($data);
        $data = TicketResource::sanitizeClientTicketData($data, preserveOwnership: true);

        $data['created_by'] = auth()->id();
        $data['created_ticket'] = auth()->user()->name;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->load('technicalSupportUsers');
        $this->record->syncAssignmentState();

        $technicalUsers = User::role(['super_admin', 'admin', 'technical_support'])
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->get();

        foreach ($technicalUsers as $user) {
            $user->notify(new NewTicketCreated($this->record));
        }
    }
}
