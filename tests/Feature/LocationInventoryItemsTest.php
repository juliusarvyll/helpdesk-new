<?php

namespace Tests\Feature;

use App\Filament\Resources\LocationResource;
use App\Filament\Resources\LocationResource\RelationManagers\InventoryItemsRelationManager;
use App\Models\InventoryItem;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationInventoryItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_has_inventory_items(): void
    {
        $location = Location::factory()->create();
        $item = InventoryItem::factory()->create(['location_id' => $location->id]);
        InventoryItem::factory()->create(['is_deleted' => true, 'location_id' => $location->id]);

        $this->assertTrue($location->inventoryItems->contains($item));
        $this->assertCount(1, $location->inventoryItems);
    }

    public function test_location_resource_registers_filament_inventory_items_table(): void
    {
        $this->assertContains(
            InventoryItemsRelationManager::class,
            LocationResource::getRelations()
        );
    }
}
