<?php

namespace Tests\Feature;

use App\Filament\Resources\LocationResource;
use App\Filament\Resources\LocationResource\RelationManagers\InventoryItemsRelationManager;
use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationInventoryItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_has_inventory_item_serial_numbers(): void
    {
        $location = Location::factory()->create();
        $item = InventoryItem::factory()->create();
        $serialNumber = InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-LOCATION-1',
            'status' => 'available',
            'location_id' => $location->id,
        ]);
        $deletedItem = InventoryItem::factory()->create(['is_deleted' => true]);
        InventoryItemSerialNumber::create([
            'inventory_item_id' => $deletedItem->id,
            'serial_number' => 'SN-LOCATION-DELETED',
            'status' => 'available',
            'location_id' => $location->id,
        ]);

        $this->assertTrue($location->inventoryItemSerialNumbers->contains($serialNumber));
        $this->assertCount(1, $location->inventoryItemSerialNumbers);
    }

    public function test_location_resource_registers_filament_inventory_items_table(): void
    {
        $this->assertContains(
            InventoryItemsRelationManager::class,
            LocationResource::getRelations()
        );
    }
}
