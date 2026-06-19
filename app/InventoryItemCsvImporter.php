<?php

namespace App;

use App\Filament\Resources\InventoryItemResource;
use App\Models\Department;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryItemCsvImporter
{
    /**
     * @param  array<string, mixed>  $rowData
     */
    public function import(array $rowData, ?Department $tenant, User $actor): InventoryItem
    {
        return DB::transaction(function () use ($rowData, $tenant, $actor): InventoryItem {
            $assignedToUserId = filled($rowData['assigned_to_user_id'] ?? null)
                ? (int) $rowData['assigned_to_user_id']
                : null;
            $status = filled($rowData['status'] ?? null)
                ? (string) $rowData['status']
                : ($assignedToUserId ? 'assigned' : 'available');
            $categoryId = $this->categoryId($rowData, $tenant);
            $serialNumbers = $this->serialNumbers($rowData);
            $serialLocationIds = $this->serialLocationIds($rowData, $tenant, $serialNumbers);

            $attributes = [
                'inventory_category_id' => $categoryId,
                'name' => $rowData['name'],
                'description' => ($rowData['description'] ?? '') ?: null,
                'status' => $status,
                'quantity' => $this->quantity($rowData, $serialNumbers),
                'unit' => ($rowData['unit'] ?? '') ?: null,
                'location_id' => null,
                'assigned_to_user_id' => $assignedToUserId,
                'department_id' => $tenant?->id,
                'metadata' => $this->metadata($rowData),
                'purchased_at' => ($rowData['purchased_at'] ?? '') ?: null,
                'warranty_expires_at' => ($rowData['warranty_expires_at'] ?? '') ?: null,
            ];

            $item = $this->inventoryItem($rowData, $attributes);
            $transactionType = $item->wasRecentlyCreated ? 'created' : 'adjusted';
            $transactionNotes = $item->wasRecentlyCreated ? 'Imported from CSV.' : 'Updated from CSV import.';

            foreach ($serialNumbers as $index => $serialNumber) {
                $this->upsertSerialNumber($item, $serialNumber, $status, $assignedToUserId, $serialLocationIds[$index] ?? null);
            }

            $item->refresh();

            InventoryTransaction::create([
                'inventory_item_id' => $item->id,
                'ticket_id' => null,
                'user_id' => $actor->id,
                'assigned_to_user_id' => $item->assigned_to_user_id,
                'type' => $transactionType,
                'quantity' => $item->quantity,
                'from_status' => null,
                'to_status' => $item->status,
                'notes' => $transactionNotes,
                'metadata' => null,
            ]);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @param  array<string, mixed>  $attributes
     */
    private function inventoryItem(array $rowData, array $attributes): InventoryItem
    {
        if (blank($rowData['asset_tag'] ?? null)) {
            return InventoryItem::create([
                ...$attributes,
                'asset_tag' => null,
            ]);
        }

        $item = InventoryItem::firstOrNew(['asset_tag' => $rowData['asset_tag']]);
        $item->fill($attributes);
        $item->is_deleted = false;
        $item->save();

        return $item;
    }

    /**
     * @param  array<string, mixed>  $rowData
     */
    private function categoryId(array $rowData, ?Department $tenant): int
    {
        if (filled($rowData['inventory_category_id'] ?? null)) {
            $category = InventoryCategory::query()->find((int) $rowData['inventory_category_id']);

            if ($category instanceof InventoryCategory) {
                if ($tenant && $category->department_id && $category->department_id !== $tenant->id) {
                    throw ValidationException::withMessages([
                        'inventory_category_id' => 'The selected category does not belong to the current department.',
                    ]);
                }

                if ($category->is_deleted) {
                    $category->forceFill(['is_deleted' => false])->save();
                }

                if ($tenant && ! $category->department_id) {
                    $category->forceFill(['department_id' => $tenant->id])->save();
                }

                return $category->id;
            }
        }

        if (blank($rowData['category_name'] ?? null)) {
            throw ValidationException::withMessages([
                'category_name' => 'The category name field is required when inventory_category_id is missing or unknown.',
            ]);
        }

        $categoryType = $this->categoryType($rowData['category_type'] ?? null);
        $parentCategoryId = $this->parentCategoryId($rowData, $categoryType, $tenant);

        $category = InventoryCategory::firstOrNew([
            'department_id' => $tenant?->id,
            'name' => trim((string) $rowData['category_name']),
            'type' => $categoryType,
            'parent_id' => $parentCategoryId,
        ]);
        $category->fill([
            'is_deleted' => false,
        ]);
        $category->save();

        return $category->id;
    }

    /**
     * @param  array<string, mixed>  $rowData
     */
    private function parentCategoryId(array $rowData, string $fallbackType, ?Department $tenant): ?int
    {
        if (blank($rowData['parent_category_name'] ?? null)) {
            return null;
        }

        $parentType = $this->categoryType(
            filled($rowData['parent_category_type'] ?? null) ? $rowData['parent_category_type'] : $fallbackType
        );

        $parentCategory = InventoryCategory::firstOrNew([
            'department_id' => $tenant?->id,
            'name' => trim((string) $rowData['parent_category_name']),
            'type' => $parentType,
            'parent_id' => null,
        ]);
        $parentCategory->fill([
            'is_deleted' => false,
        ]);
        $parentCategory->save();

        return $parentCategory->id;
    }

    private function categoryType(mixed $type): string
    {
        if (blank($type)) {
            return 'asset';
        }

        $type = Str::of((string) $type)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        if (blank($type)) {
            throw ValidationException::withMessages([
                'category_type' => 'The category type must contain at least one letter or number.',
            ]);
        }

        return $type;
    }

    private function locationIdFromName(string $name, ?Department $tenant): int
    {
        return Location::firstOrCreate(
            [
                'name' => trim($name),
                'department_id' => $tenant?->id,
            ],
            ['is_deleted' => false]
        )->id;
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @return array<string, string|null>|null
     */
    private function metadata(array $rowData): ?array
    {
        if (blank($rowData['metadata'] ?? null)) {
            return null;
        }

        $metadata = json_decode((string) $rowData['metadata'], true);

        if (! is_array($metadata)) {
            return null;
        }

        return InventoryItemResource::metadataForKeyValue($metadata);
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @return array<int, string>
     */
    private function serialNumbers(array $rowData): array
    {
        if (blank($rowData['serial_number'] ?? null)) {
            return [];
        }

        return $this->delimitedValues((string) $rowData['serial_number'], splitCommas: true)
            ->unique()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @param  array<int, string>  $serialNumbers
     * @return array<int, int|null>
     */
    private function serialLocationIds(array $rowData, ?Department $tenant, array $serialNumbers): array
    {
        if ($serialNumbers === []) {
            return [];
        }

        if (blank($rowData['location'] ?? null)) {
            return array_fill(0, count($serialNumbers), null);
        }

        $rawLocation = (string) $rowData['location'];
        $locations = $this->delimitedValues($rawLocation, splitCommas: false);

        if (count($locations) === 1 && str_contains($rawLocation, ',')) {
            $commaLocations = $this->delimitedValues($rawLocation, splitCommas: true);

            if (count($commaLocations) === count($serialNumbers)) {
                $locations = $commaLocations;
            }
        }

        if (count($locations) === count($serialNumbers)) {
            return collect($locations)
                ->map(fn (string $location): int => $this->locationIdFromName($location, $tenant))
                ->all();
        }

        $locationId = $this->locationIdFromName($rawLocation, $tenant);

        return array_fill(0, count($serialNumbers), $locationId);
    }

    /**
     * @return Collection<int, string>
     */
    private function delimitedValues(string $value, bool $splitCommas): Collection
    {
        $delimiters = ["\r\n", "\r", "\n", ';', '|'];

        if ($splitCommas) {
            $delimiters[] = ',';
        }

        return Str::of($value)
            ->replace($delimiters, ',')
            ->explode(',')
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @param  array<int, string>  $serialNumbers
     */
    private function quantity(array $rowData, array $serialNumbers): int
    {
        $quantity = filled($rowData['quantity'] ?? null)
            ? (int) $rowData['quantity']
            : 1;

        return max($quantity, count($serialNumbers), 1);
    }

    private function upsertSerialNumber(InventoryItem $item, string $serialNumber, string $status, ?int $assignedToUserId, ?int $locationId): ?InventoryItemSerialNumber
    {
        $serial = InventoryItemSerialNumber::query()
            ->with('inventoryItem')
            ->firstOrNew(['serial_number' => $serialNumber]);

        if ($serial->exists && $serial->inventory_item_id !== $item->id) {
            if (! $serial->inventoryItem?->is_deleted) {
                Log::warning('Inventory item CSV serial number skipped because it belongs to another active item.', [
                    'serial_number' => $serialNumber,
                    'target_inventory_item_id' => $item->id,
                    'target_asset_tag' => $item->asset_tag,
                    'existing_inventory_item_id' => $serial->inventory_item_id,
                    'existing_asset_tag' => $serial->inventoryItem?->asset_tag,
                ]);

                return null;
            }
        }

        $serial->fill([
            'inventory_item_id' => $item->id,
            'status' => $status,
            'assigned_to_user_id' => $assignedToUserId,
            'location_id' => $locationId,
        ]);
        $serial->save();

        return $serial;
    }
}
