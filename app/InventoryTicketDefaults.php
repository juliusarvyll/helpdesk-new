<?php

namespace App;

use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\User;

class InventoryTicketDefaults
{
    public function serialNumberId(InventoryItem $inventoryItem): ?int
    {
        if ($inventoryItem->serialNumbers()->count() !== 1) {
            return null;
        }

        return $inventoryItem->serialNumbers()->value('id');
    }

    public function clientId(InventoryItem $inventoryItem, ?int $serialNumberId, User $fallbackUser): ?int
    {
        $serialNumber = $this->serialNumber($inventoryItem, $serialNumberId);

        return $serialNumber?->assigned_to_user_id
            ?? $inventoryItem->assigned_to_user_id
            ?? $fallbackUser->id;
    }

    public function subject(InventoryItem $inventoryItem, ?int $serialNumberId): string
    {
        $serialNumber = $this->serialNumber($inventoryItem, $serialNumberId);

        if ($serialNumber) {
            return "Issue for {$inventoryItem->name} ({$serialNumber->serial_number})";
        }

        return "Issue for {$inventoryItem->name}";
    }

    public function description(InventoryItem $inventoryItem, ?int $serialNumberId): string
    {
        $serialNumber = $this->serialNumber($inventoryItem, $serialNumberId);

        return collect([
            "Item: {$inventoryItem->name}",
            $inventoryItem->asset_tag ? "Asset Tag: {$inventoryItem->asset_tag}" : null,
            $serialNumber ? "Serial Number: {$serialNumber->serial_number}" : null,
            $this->locationName($inventoryItem, $serialNumber) ? "Location: {$this->locationName($inventoryItem, $serialNumber)}" : null,
            $inventoryItem->assignedToUser?->name ? "Assigned To: {$inventoryItem->assignedToUser->name}" : null,
            '',
            'Issue Details:',
        ])->filter(fn (?string $line): bool => $line !== null)->implode(PHP_EOL);
    }

    private function serialNumber(InventoryItem $inventoryItem, ?int $serialNumberId): ?InventoryItemSerialNumber
    {
        if (blank($serialNumberId)) {
            return null;
        }

        return $inventoryItem->serialNumbers()
            ->with('location')
            ->whereKey($serialNumberId)
            ->first();
    }

    private function locationName(InventoryItem $inventoryItem, ?InventoryItemSerialNumber $serialNumber): ?string
    {
        return $serialNumber?->location?->name
            ?? $inventoryItem->location?->name;
    }
}
