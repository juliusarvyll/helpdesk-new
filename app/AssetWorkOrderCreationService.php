<?php

namespace App;

use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AssetWorkOrderCreationService
{
    public function __construct(
        public TicketCreationService $tickets,
        public JobOrderCreationService $jobOrders,
    ) {}

    public function create(InventoryItem $inventoryItem, array $data, User $creator): Ticket|JobOrder
    {
        $payload = [
            ...$data,
            'inventory_item_id' => $inventoryItem->id,
            'asset_id' => $inventoryItem->asset_tag,
            'asset_name' => $inventoryItem->name,
            'department_id' => $inventoryItem->department_id ?? $data['department_id'] ?? $creator->department_id,
            'source' => $data['source'] ?? 'inventory',
        ];

        if ($inventoryItem->is_it_asset) {
            return $this->tickets->create($payload, $creator);
        }

        unset($payload['asset_id'], $payload['asset_name'], $payload['client_comments'], $payload['issue_id'], $payload['category']);

        return $this->jobOrders->create($payload, $creator);
    }

    public function typeFor(Model $work): string
    {
        return $work instanceof Ticket ? 'ticket' : 'job_order';
    }
}
