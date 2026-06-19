<?php

namespace Tests\Feature;

use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InventoryItemResource\Pages\CreateInventoryItem;
use App\Filament\Resources\InventoryItemResource\Pages\EditInventoryItem;
use App\Filament\Resources\InventoryItemResource\Pages\ViewInventoryItem;
use App\Filament\Resources\InventoryItemResource\RelationManagers\SerialNumbersRelationManager;
use App\InventoryMovementService;
use App\Models\Department;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\Location;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as PermissionRole;
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
            'status' => 'available',
        ]);

        InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-1',
            'status' => 'available',
        ]);

        $item->refresh();
        $this->assertSame(1, $item->quantity);
        $this->assertSame('available', $item->status);

        $assignedSerialNumber = InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-2',
            'status' => 'assigned',
        ]);

        $item->refresh();
        $this->assertSame(2, $item->quantity);
        $this->assertSame('assigned', $item->status);

        $assignedSerialNumber->update(['status' => 'in_repair']);

        $item->refresh();
        $this->assertSame(2, $item->quantity);
        $this->assertSame('in_repair', $item->status);
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

    public function test_inventory_item_can_be_created_with_serial_numbers(): void
    {
        $department = Department::factory()->create();
        $category = InventoryCategory::factory()->create(['department_id' => $department->id]);
        $location = Location::factory()->create(['department_id' => $department->id]);
        $assignedUser = User::factory()->create([
            'department_id' => $department->id,
            'status' => 1,
            'is_deleted' => 0,
        ]);
        $actor = $this->panelUser($department);

        Filament::setTenant($department, isQuiet: true);

        Livewire::actingAs($actor)
            ->test(CreateInventoryItem::class)
            ->set('data.inventory_category_id', $category->id)
            ->set('data.asset_tag', 'AST-SERIAL-CREATE')
            ->set('data.name', 'Serialized Laptop')
            ->set('data.quantity', 1)
            ->set('data.serialNumbers', [
                [
                    'serial_number' => 'SN-CREATE-001',
                    'status' => 'available',
                    'location_id' => $location->id,
                    'assigned_to_user_id' => null,
                ],
                [
                    'serial_number' => 'SN-CREATE-002',
                    'status' => 'assigned',
                    'location_id' => $location->id,
                    'assigned_to_user_id' => $assignedUser->id,
                ],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $item = InventoryItem::query()
            ->where('asset_tag', 'AST-SERIAL-CREATE')
            ->firstOrFail();

        $this->assertSame(2, $item->quantity);
        $this->assertSame('assigned', $item->status);
        $this->assertSame($department->id, $item->department_id);
        $this->assertDatabaseHas('inventory_item_serial_numbers', [
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-CREATE-001',
            'status' => 'available',
            'location_id' => $location->id,
            'assigned_to_user_id' => null,
        ]);
        $this->assertDatabaseHas('inventory_item_serial_numbers', [
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-CREATE-002',
            'status' => 'assigned',
            'location_id' => $location->id,
            'assigned_to_user_id' => $assignedUser->id,
        ]);
    }

    public function test_serial_numbers_form_field_is_only_visible_when_creating_inventory_items(): void
    {
        $department = Department::factory()->create();
        $category = InventoryCategory::factory()->create(['department_id' => $department->id]);
        $actor = $this->panelUser($department);
        $item = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'department_id' => $department->id,
        ]);

        Filament::setTenant($department, isQuiet: true);

        Livewire::actingAs($actor)
            ->test(CreateInventoryItem::class)
            ->assertSchemaComponentVisible('serialNumbers');

        Livewire::actingAs($actor)
            ->test(EditInventoryItem::class, ['record' => $item->getRouteKey()])
            ->assertSchemaComponentHidden('serialNumbers');

        Livewire::actingAs($actor)
            ->test(ViewInventoryItem::class, ['record' => $item->getRouteKey()])
            ->assertSchemaComponentHidden('serialNumbers');
    }

    public function test_serialized_inventory_item_header_actions_remain_visible(): void
    {
        $department = Department::factory()->create();
        $category = InventoryCategory::factory()->create(['department_id' => $department->id]);
        $assignedUser = User::factory()->create([
            'department_id' => $department->id,
            'status' => 1,
            'is_deleted' => 0,
        ]);
        $actor = $this->panelUser($department);
        $item = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'department_id' => $department->id,
            'quantity' => 0,
            'status' => 'available',
        ]);
        $serialNumber = InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-ACTION-001',
            'status' => 'available',
        ]);

        Filament::setTenant($department, isQuiet: true);

        Livewire::actingAs($actor)
            ->test(ViewInventoryItem::class, ['record' => $item->getRouteKey()])
            ->assertActionVisible('createTicket')
            ->assertActionVisible('assign')
            ->assertActionVisible('transfer')
            ->assertActionVisible('repair')
            ->assertActionVisible('retire')
            ->assertActionHidden('adjustStock')
            ->callAction('assign', [
                'inventory_item_serial_number_id' => $serialNumber->id,
                'assigned_to_user_id' => $assignedUser->id,
                'ticket_id' => null,
                'notes' => 'Issued by serial action',
            ])
            ->assertHasNoErrors();

        $serialNumber->refresh();
        $item->refresh();

        $this->assertSame('assigned', $serialNumber->status);
        $this->assertSame($assignedUser->id, $serialNumber->assigned_to_user_id);
        $this->assertSame('assigned', $item->status);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'type' => 'assigned',
            'from_status' => 'available',
            'to_status' => 'assigned',
            'notes' => 'Issued by serial action',
        ]);
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

    private function panelUser(Department $department): User
    {
        PermissionRole::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'department_id' => $department->id,
            'status' => 1,
            'is_deleted' => 0,
        ]);
        $user->assignRole('super_admin');
        $user->departments()->attach($department);

        return $user;
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
