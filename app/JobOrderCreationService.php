<?php

namespace App;

use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobOrderCreationService
{
    public function create(array $data, User $creator): JobOrder
    {
        return DB::transaction(function () use ($data, $creator): JobOrder {
            $this->validateInventorySerial($data);

            $client = filled($data['client_id'] ?? null) ? User::query()->find($data['client_id']) : $creator;

            if (filled($data['client_id'] ?? null) && ! $client) {
                throw ValidationException::withMessages(['client_id' => 'The selected requestor does not exist.']);
            }
            $departmentId = $data['department_id'] ?? $client?->department_id ?? $creator->department_id;

            if (! $departmentId) {
                throw ValidationException::withMessages(['department_id' => 'A department is required to create a job order.']);
            }

            $this->validateDepartmentOwnership($data, $creator, $client, (int) $departmentId);

            return JobOrder::create([
                ...$data,
                'department_id' => $departmentId,
                'client_id' => $client?->id,
                'created_by' => $creator->id,
                'requested_by_name' => $data['requested_by_name'] ?? $client?->name ?? $creator->name,
                'priority' => $data['priority'] ?? 'normal',
                'status' => $data['status'] ?? JobOrderStatus::Active,
                'source' => $data['source'] ?? 'manual',
            ]);
        });
    }

    private function validateDepartmentOwnership(array $data, User $creator, ?User $client, int $departmentId): void
    {
        if (! User::query()->whereKey($creator->getKey())->canAccessDepartment($departmentId)->exists()) {
            throw ValidationException::withMessages(['department_id' => 'You cannot create a job order for this department.']);
        }

        if ($client && ! User::query()->whereKey($client->getKey())->canAccessDepartment($departmentId)->exists()) {
            throw ValidationException::withMessages(['client_id' => 'The requestor does not belong to this department.']);
        }

        if (filled($data['assigned_to_user_id'] ?? null) && ! User::query()->whereKey($data['assigned_to_user_id'])->canAccessDepartment($departmentId)->exists()) {
            throw ValidationException::withMessages(['assigned_to_user_id' => 'The assigned user cannot access this department.']);
        }

        if (filled($data['inventory_item_id'] ?? null) && InventoryItem::query()->whereKey($data['inventory_item_id'])->where('department_id', '!=', $departmentId)->exists()) {
            throw ValidationException::withMessages(['inventory_item_id' => 'The selected asset does not belong to this department.']);
        }
    }

    private function validateInventorySerial(array $data): void
    {
        if (blank($data['inventory_item_id'] ?? null)) {
            return;
        }

        $inventoryItem = InventoryItem::query()->with('serialNumbers')->findOrFail($data['inventory_item_id']);
        $serialNumberId = $data['inventory_item_serial_number_id'] ?? null;

        if ($inventoryItem->is_it_asset) {
            throw ValidationException::withMessages([
                'inventory_item_id' => 'IT assets must be serviced through a Helpdesk Ticket.',
            ]);
        }

        if ($inventoryItem->serialNumbers->isNotEmpty() && blank($serialNumberId)) {
            throw ValidationException::withMessages(['inventory_item_serial_number_id' => 'Select the serial number for this asset job order.']);
        }

        if (blank($serialNumberId)) {
            return;
        }

        if (! $inventoryItem->serialNumbers->contains('id', (int) $serialNumberId)) {
            throw ValidationException::withMessages(['inventory_item_serial_number_id' => 'The selected serial number does not belong to the selected inventory item.']);
        }

        $serialNumber = InventoryItemSerialNumber::query()->lockForUpdate()->findOrFail($serialNumberId);

        if ($serialNumber->hasOpenJobOrder()) {
            throw ValidationException::withMessages(['inventory_item_serial_number_id' => 'This serial number already has an open job order.']);
        }
    }
}
