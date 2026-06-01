<?php

namespace Tests\Feature;

use App\InventoryTicketDefaults;
use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTicketDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_auto_selects_the_only_serial_number(): void
    {
        $item = InventoryItem::factory()->create();
        $serialNumber = InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-ONLY',
            'status' => 'available',
        ]);

        $this->assertSame($serialNumber->id, app(InventoryTicketDefaults::class)->serialNumberId($item));
    }

    public function test_it_does_not_auto_select_when_multiple_serial_numbers_exist(): void
    {
        $item = InventoryItem::factory()->create();

        InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-1',
            'status' => 'available',
        ]);
        InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-2',
            'status' => 'available',
        ]);

        $this->assertNull(app(InventoryTicketDefaults::class)->serialNumberId($item));
    }

    public function test_it_prefers_the_serial_assigned_user_for_the_ticket_client(): void
    {
        $fallbackUser = User::factory()->create();
        $itemAssignedUser = User::factory()->create();
        $serialAssignedUser = User::factory()->create();
        $item = InventoryItem::factory()->create([
            'assigned_to_user_id' => $itemAssignedUser->id,
        ]);
        $serialNumber = InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-ASSIGNED',
            'status' => 'assigned',
            'assigned_to_user_id' => $serialAssignedUser->id,
        ]);

        $this->assertSame(
            $serialAssignedUser->id,
            app(InventoryTicketDefaults::class)->clientId($item, $serialNumber->id, $fallbackUser),
        );
    }

    public function test_it_builds_subject_and_description_from_inventory_context(): void
    {
        $assignedUser = User::factory()->create(['name' => 'Maria Santos']);
        $location = Location::factory()->create(['name' => 'Main Office']);
        $item = InventoryItem::factory()->create([
            'asset_tag' => 'AST-9001',
            'name' => 'Dell Latitude',
            'location_id' => $location->id,
            'assigned_to_user_id' => $assignedUser->id,
        ]);
        $serialNumber = InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-9001',
            'status' => 'assigned',
        ]);

        $defaults = app(InventoryTicketDefaults::class);

        $this->assertSame('Issue for Dell Latitude (SN-9001)', $defaults->subject($item, $serialNumber->id));
        $this->assertStringContainsString('Asset Tag: AST-9001', $defaults->description($item, $serialNumber->id));
        $this->assertStringContainsString('Serial Number: SN-9001', $defaults->description($item, $serialNumber->id));
        $this->assertStringContainsString('Location: Main Office', $defaults->description($item, $serialNumber->id));
        $this->assertStringContainsString('Assigned To: Maria Santos', $defaults->description($item, $serialNumber->id));
    }

    public function test_it_prefers_serial_number_location_for_description(): void
    {
        $itemLocation = Location::factory()->create(['name' => 'Item Office']);
        $serialLocation = Location::factory()->create(['name' => 'Serial Office']);
        $item = InventoryItem::factory()->create([
            'asset_tag' => 'AST-9002',
            'name' => 'Dell Latitude',
            'location_id' => $itemLocation->id,
        ]);
        $serialNumber = InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-9002',
            'status' => 'available',
            'location_id' => $serialLocation->id,
        ]);

        $description = app(InventoryTicketDefaults::class)->description($item, $serialNumber->id);

        $this->assertStringContainsString('Location: Serial Office', $description);
        $this->assertStringNotContainsString('Location: Item Office', $description);
    }
}
