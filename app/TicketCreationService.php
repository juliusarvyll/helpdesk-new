<?php

namespace App;

use App\Filament\Resources\TicketResource;
use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewTicketCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketCreationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $creator): Ticket
    {
        return DB::transaction(function () use ($data, $creator): Ticket {
            unset($data['technicalSupportUsers'], $data['category']);

            $this->validateInventorySerial($data);

            $data = TicketResource::sanitizeTechnicalSupportAssignmentData($data, allowAssignment: false);
            $data = TicketResource::assignCreatorClientAndDepartmentData($data);
            $data = TicketResource::sanitizeClientTicketData($data, preserveOwnership: true);

            $data['created_by'] = $creator->id;
            $data['created_ticket'] = $creator->name;
            $data['status'] ??= TicketStatus::Active;

            $ticket = Ticket::create($data);

            $ticket->load('technicalSupportUsers');
            $ticket->syncAssignmentState();
            $this->notifyUsers($ticket);

            return $ticket;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateInventorySerial(array $data): void
    {
        if (blank($data['inventory_item_id'] ?? null)) {
            return;
        }

        $inventoryItem = InventoryItem::query()
            ->with('serialNumbers')
            ->findOrFail($data['inventory_item_id']);

        if (blank($data['inventory_item_serial_number_id'] ?? null)) {
            throw ValidationException::withMessages([
                'inventory_item_serial_number_id' => 'Select the serial number for this inventory ticket.',
            ]);
        }

        if (! $inventoryItem->serialNumbers->contains('id', (int) $data['inventory_item_serial_number_id'])) {
            throw ValidationException::withMessages([
                'inventory_item_serial_number_id' => 'The selected serial number does not belong to the selected inventory item.',
            ]);
        }

        $serialNumber = InventoryItemSerialNumber::query()
            ->findOrFail($data['inventory_item_serial_number_id']);

        if ($serialNumber->hasOpenTicket()) {
            throw ValidationException::withMessages([
                'inventory_item_serial_number_id' => 'This serial number already has an open ticket.',
            ]);
        }
    }

    private function notifyUsers(Ticket $ticket): void
    {
        $technicalUsers = User::role(['super_admin', 'admin', 'technical_support'])
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->get();

        foreach ($technicalUsers as $user) {
            $user->notify(new NewTicketCreated($ticket));
        }
    }
}
