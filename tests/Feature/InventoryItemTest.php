<?php

namespace Tests\Feature;

use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InventoryItemResource\RelationManagers\SerialNumbersRelationManager;
use App\InventoryMovementService;
use App\Models\Department;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_inventory_item(): void
    {
        $category = InventoryCategory::factory()->create();

        $item = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Test Laptop',
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'name' => 'Test Laptop',
            'status' => 'available',
        ]);
    }

    public function test_inventory_item_belongs_to_category(): void
    {
        $category = InventoryCategory::factory()->create(['name' => 'Laptops']);
        $item = InventoryItem::factory()->create(['inventory_category_id' => $category->id]);

        $this->assertEquals('Laptops', $item->category->name);
    }

    public function test_inventory_item_can_be_assigned_to_user(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::factory()->create([
            'status' => 'assigned',
            'assigned_to_user_id' => $user->id,
        ]);

        $this->assertEquals($user->id, $item->assignedToUser->id);
        $this->assertEquals('assigned', $item->status);
    }

    public function test_inventory_item_can_be_assigned_to_department(): void
    {
        $department = Department::factory()->create();
        $item = InventoryItem::factory()->create(['department_id' => $department->id]);

        $this->assertEquals($department->id, $item->department->id);
    }

    public function test_inventory_item_metadata_is_cast_to_array(): void
    {
        $item = InventoryItem::factory()->create([
            'metadata' => ['cpu' => 'Intel i7', 'ram' => '16GB'],
        ]);

        $this->assertIsArray($item->metadata);
        $this->assertEquals('Intel i7', $item->metadata['cpu']);
    }

    public function test_inventory_item_quantity_updates_when_serial_numbers_are_added(): void
    {
        $item = InventoryItem::factory()->create([
            'quantity' => 0,
        ]);

        InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-1',
            'status' => 'available',
        ]);

        $item->refresh();
        $this->assertSame(1, $item->quantity);

        InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-2',
            'status' => 'available',
        ]);

        $item->refresh();
        $this->assertSame(2, $item->quantity);
    }

    public function test_inventory_item_quantity_updates_when_serial_number_moves_between_items(): void
    {
        $sourceItem = InventoryItem::factory()->create([
            'quantity' => 0,
        ]);
        $targetItem = InventoryItem::factory()->create([
            'quantity' => 0,
        ]);
        $serialNumber = InventoryItemSerialNumber::create([
            'inventory_item_id' => $sourceItem->id,
            'serial_number' => 'SN-MOVE',
            'status' => 'available',
        ]);

        $sourceItem->refresh();
        $targetItem->refresh();

        $this->assertSame(1, $sourceItem->quantity);
        $this->assertSame(0, $targetItem->quantity);

        $serialNumber->update([
            'inventory_item_id' => $targetItem->id,
        ]);

        $sourceItem->refresh();
        $targetItem->refresh();

        $this->assertSame(0, $sourceItem->quantity);
        $this->assertSame(1, $targetItem->quantity);
    }

    public function test_inventory_item_soft_delete(): void
    {
        $item = InventoryItem::factory()->create();

        $item->update(['is_deleted' => true]);

        $this->assertTrue($item->is_deleted);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'is_deleted' => true,
        ]);
    }

    public function test_consumable_item_has_quantity(): void
    {
        $item = InventoryItem::factory()->consumable()->create([
            'quantity' => 50,
            'unit' => 'pcs',
        ]);

        $this->assertEquals(50, $item->quantity);
        $this->assertEquals('pcs', $item->unit);
    }

    public function test_inventory_item_resource_registers_view_page(): void
    {
        $this->assertArrayHasKey('view', InventoryItemResource::getPages());
    }

    public function test_inventory_item_resource_registers_serial_numbers_relation_manager(): void
    {
        $this->assertContains(
            SerialNumbersRelationManager::class,
            InventoryItemResource::getRelations(),
        );
    }

    public function test_assigning_inventory_item_updates_state_and_records_transaction(): void
    {
        $actor = User::factory()->create();
        $assignedUser = User::factory()->create();
        $item = InventoryItem::factory()->create();

        app(InventoryMovementService::class)->assign($item, $actor, $assignedUser, notes: 'Issued as replacement');

        $item->refresh();

        $this->assertEquals('assigned', $item->status);
        $this->assertEquals($assignedUser->id, $item->assigned_to_user_id);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'user_id' => $actor->id,
            'assigned_to_user_id' => $assignedUser->id,
            'type' => 'assigned',
            'from_status' => 'available',
            'to_status' => 'assigned',
            'notes' => 'Issued as replacement',
        ]);
    }

    public function test_consuming_stock_decrements_quantity_and_records_transaction(): void
    {
        $actor = User::factory()->create();
        $item = InventoryItem::factory()->consumable()->create(['quantity' => 10]);

        app(InventoryMovementService::class)->consume($item, $actor, 4);

        $item->refresh();

        $this->assertEquals(6, $item->quantity);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'user_id' => $actor->id,
            'type' => 'consumed',
            'quantity' => 4,
        ]);
    }

    public function test_consuming_more_than_available_stock_fails(): void
    {
        $actor = User::factory()->create();
        $item = InventoryItem::factory()->consumable()->create(['quantity' => 2]);

        $this->expectException(ValidationException::class);

        try {
            app(InventoryMovementService::class)->consume($item, $actor, 3);
        } catch (ValidationException $exception) {
            $this->assertDatabaseMissing('inventory_transactions', [
                'inventory_item_id' => $item->id,
                'type' => 'consumed',
            ]);

            throw $exception;
        }
    }

    public function test_adjusting_stock_sets_quantity_and_records_old_and_new_values(): void
    {
        $actor = User::factory()->create();
        $item = InventoryItem::factory()->consumable()->create(['quantity' => 8]);

        $transaction = app(InventoryMovementService::class)->adjust($item, $actor, 12, 'Physical count');

        $item->refresh();
        $transaction->refresh();

        $this->assertEquals(12, $item->quantity);
        $this->assertEquals('adjusted', $transaction->type);
        $this->assertEquals(8, $transaction->metadata['old_quantity']);
        $this->assertEquals(12, $transaction->metadata['new_quantity']);
    }
}
