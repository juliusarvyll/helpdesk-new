<?php

namespace App;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryMovementService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function assign(InventoryItem $inventoryItem, User $actor, User $assignedToUser, ?int $ticketId = null, ?string $notes = null, array $metadata = []): InventoryTransaction
    {
        return $this->move(
            inventoryItem: $inventoryItem,
            actor: $actor,
            type: 'assigned',
            quantity: 1,
            toStatus: 'assigned',
            assignedToUserId: $assignedToUser->id,
            ticketId: $ticketId,
            notes: $notes,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function return(InventoryItem $inventoryItem, User $actor, ?int $ticketId = null, ?string $notes = null, array $metadata = []): InventoryTransaction
    {
        return $this->move(
            inventoryItem: $inventoryItem,
            actor: $actor,
            type: 'returned',
            quantity: 1,
            toStatus: 'available',
            assignedToUserId: null,
            ticketId: $ticketId,
            notes: $notes,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function consume(InventoryItem $inventoryItem, User $actor, int $quantity, ?int $ticketId = null, ?string $notes = null, array $metadata = []): InventoryTransaction
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'The quantity must be at least 1.',
            ]);
        }

        return $this->move(
            inventoryItem: $inventoryItem,
            actor: $actor,
            type: 'consumed',
            quantity: $quantity,
            toStatus: null,
            assignedToUserId: $inventoryItem->assigned_to_user_id,
            ticketId: $ticketId,
            notes: $notes,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function transfer(InventoryItem $inventoryItem, User $actor, ?int $locationId, ?int $departmentId = null, ?int $ticketId = null, ?string $notes = null, array $metadata = []): InventoryTransaction
    {
        return $this->move(
            inventoryItem: $inventoryItem,
            actor: $actor,
            type: 'transferred',
            quantity: 1,
            toStatus: null,
            assignedToUserId: $inventoryItem->assigned_to_user_id,
            ticketId: $ticketId,
            notes: $notes,
            metadata: [
                ...$metadata,
                'location_id' => $locationId,
                'department_id' => $departmentId,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function repair(InventoryItem $inventoryItem, User $actor, ?int $ticketId = null, ?string $notes = null, array $metadata = []): InventoryTransaction
    {
        return $this->move($inventoryItem, $actor, 'repaired', 1, 'in_repair', $inventoryItem->assigned_to_user_id, $ticketId, $notes, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function retire(InventoryItem $inventoryItem, User $actor, ?int $ticketId = null, ?string $notes = null, array $metadata = []): InventoryTransaction
    {
        return $this->move($inventoryItem, $actor, 'retired', 1, 'retired', null, $ticketId, $notes, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function adjust(InventoryItem $inventoryItem, User $actor, int $newQuantity, ?string $notes = null, array $metadata = []): InventoryTransaction
    {
        if ($newQuantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'The quantity must be at least 0.',
            ]);
        }

        return DB::transaction(function () use ($inventoryItem, $actor, $newQuantity, $notes, $metadata): InventoryTransaction {
            $lockedItem = InventoryItem::query()->lockForUpdate()->findOrFail($inventoryItem->id);
            $oldQuantity = $lockedItem->quantity;

            $lockedItem->forceFill(['quantity' => $newQuantity])->save();

            return InventoryTransaction::create([
                'inventory_item_id' => $lockedItem->id,
                'ticket_id' => null,
                'user_id' => $actor->id,
                'assigned_to_user_id' => $lockedItem->assigned_to_user_id,
                'type' => 'adjusted',
                'quantity' => abs($newQuantity - $oldQuantity),
                'from_status' => $lockedItem->status,
                'to_status' => $lockedItem->status,
                'notes' => $notes,
                'metadata' => [
                    ...$metadata,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                ],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function move(
        InventoryItem $inventoryItem,
        User $actor,
        string $type,
        int $quantity,
        ?string $toStatus,
        ?int $assignedToUserId,
        ?int $ticketId,
        ?string $notes,
        array $metadata,
    ): InventoryTransaction {
        return DB::transaction(function () use ($inventoryItem, $actor, $type, $quantity, $toStatus, $assignedToUserId, $ticketId, $notes, $metadata): InventoryTransaction {
            $lockedItem = InventoryItem::query()->lockForUpdate()->findOrFail($inventoryItem->id);
            $fromStatus = $lockedItem->status;
            $updates = [];

            if ($type === 'consumed') {
                if ($quantity > $lockedItem->quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => 'The requested quantity exceeds available stock.',
                    ]);
                }

                $updates['quantity'] = $lockedItem->quantity - $quantity;
            }

            if ($toStatus !== null) {
                $updates['status'] = $toStatus;
            }

            if (in_array($type, ['assigned', 'returned', 'retired'], true)) {
                $updates['assigned_to_user_id'] = $assignedToUserId;
            }

            if ($ticketId !== null) {
                $updates['current_ticket_id'] = $ticketId;
            }

            if ($type === 'transferred') {
                $updates['location_id'] = $metadata['location_id'] ?? null;

                if (array_key_exists('department_id', $metadata)) {
                    $updates['department_id'] = $metadata['department_id'];
                }
            }

            $lockedItem->forceFill($updates)->save();

            return InventoryTransaction::create([
                'inventory_item_id' => $lockedItem->id,
                'ticket_id' => $ticketId,
                'user_id' => $actor->id,
                'assigned_to_user_id' => $lockedItem->assigned_to_user_id,
                'type' => $type,
                'quantity' => $quantity,
                'from_status' => $fromStatus,
                'to_status' => $lockedItem->status,
                'notes' => $notes,
                'metadata' => $metadata ?: null,
            ]);
        });
    }
}
