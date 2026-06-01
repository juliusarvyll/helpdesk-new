<?php

namespace Tests\Feature;

use App\InventoryItemCsvImporter;
use App\Jobs\ImportInventoryItemsFromCsv;
use App\Models\Department;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryItemCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_serial_number_is_assigned_to_imported_item_assignee(): void
    {
        $actor = User::factory()->create();
        $assignedUser = User::factory()->create();
        $tenant = Department::factory()->create();
        $category = InventoryCategory::factory()->create();

        $item = app(InventoryItemCsvImporter::class)->import([
            'inventory_category_id' => $category->id,
            'asset_tag' => 'AST-1001',
            'name' => 'Laptop',
            'description' => '',
            'status' => '',
            'quantity' => '1',
            'unit' => '',
            'location' => '',
            'assigned_to_user_id' => (string) $assignedUser->id,
            'metadata' => '',
            'purchased_at' => '',
            'warranty_expires_at' => '',
            'serial_number' => 'SN-1001',
        ], $tenant, $actor);

        $serialNumber = $item->serialNumbers()->firstOrFail();

        $this->assertEquals('assigned', $item->status);
        $this->assertEquals($assignedUser->id, $item->assigned_to_user_id);
        $this->assertEquals('assigned', $serialNumber->status);
        $this->assertEquals($assignedUser->id, $serialNumber->assigned_to_user_id);
    }

    public function test_imported_serial_number_receives_imported_location(): void
    {
        $actor = User::factory()->create();
        $tenant = Department::factory()->create();
        $category = InventoryCategory::factory()->create();

        $item = app(InventoryItemCsvImporter::class)->import([
            'inventory_category_id' => $category->id,
            'asset_tag' => 'AST-1001-LOC',
            'name' => 'Laptop',
            'description' => '',
            'status' => 'available',
            'quantity' => '1',
            'unit' => '',
            'location' => 'Main Office',
            'assigned_to_user_id' => '',
            'metadata' => '',
            'purchased_at' => '',
            'warranty_expires_at' => '',
            'serial_number' => 'SN-1001-LOC',
        ], $tenant, $actor);

        $serialNumber = $item->serialNumbers()->with('location')->firstOrFail();

        $this->assertNull($item->location_id);
        $this->assertEquals('Main Office', $serialNumber->location->name);
    }

    public function test_import_can_auto_assign_multiple_serial_numbers_from_one_row(): void
    {
        $actor = User::factory()->create();
        $assignedUser = User::factory()->create();
        $tenant = Department::factory()->create();
        $category = InventoryCategory::factory()->create();

        $item = app(InventoryItemCsvImporter::class)->import([
            'inventory_category_id' => $category->id,
            'asset_tag' => 'AST-1002',
            'name' => 'Monitor Bundle',
            'description' => '',
            'status' => 'assigned',
            'quantity' => '2',
            'unit' => 'pcs',
            'location' => '',
            'assigned_to_user_id' => (string) $assignedUser->id,
            'metadata' => '',
            'purchased_at' => '',
            'warranty_expires_at' => '',
            'serial_number' => 'SN-2001, SN-2002',
        ], $tenant, $actor);

        $serialNumbers = $item->serialNumbers()->orderBy('serial_number')->get();

        $this->assertCount(2, $serialNumbers);
        $this->assertEquals(['SN-2001', 'SN-2002'], $serialNumbers->pluck('serial_number')->all());
        $this->assertTrue($serialNumbers->every(fn ($serialNumber): bool => $serialNumber->assigned_to_user_id === $assignedUser->id));
        $this->assertTrue($serialNumbers->every(fn ($serialNumber): bool => $serialNumber->status === 'assigned'));
    }

    public function test_import_sets_quantity_to_serial_number_count_when_serials_exceed_quantity(): void
    {
        $actor = User::factory()->create();
        $tenant = Department::factory()->create();
        $category = InventoryCategory::factory()->create();

        $item = app(InventoryItemCsvImporter::class)->import([
            'inventory_category_id' => $category->id,
            'asset_tag' => 'AST-2003',
            'name' => 'Laptop Set',
            'description' => '',
            'status' => 'available',
            'quantity' => '1',
            'unit' => 'pcs',
            'location' => '',
            'assigned_to_user_id' => '',
            'metadata' => '',
            'purchased_at' => '',
            'warranty_expires_at' => '',
            'serial_number' => 'SN-2003-A, SN-2003-B, SN-2003-C',
        ], $tenant, $actor);

        $this->assertEquals(3, $item->quantity);
        $this->assertEquals(3, $item->serialNumbers()->count());
    }

    public function test_import_updates_existing_item_when_asset_tag_already_exists(): void
    {
        $actor = User::factory()->create();
        $assignedUser = User::factory()->create();
        $tenant = Department::factory()->create();
        $category = InventoryCategory::factory()->create();
        $item = InventoryItem::factory()->create([
            'asset_tag' => 'AST-0001',
            'inventory_category_id' => $category->id,
            'name' => 'Old Name',
            'quantity' => 1,
        ]);

        $importedItem = app(InventoryItemCsvImporter::class)->import([
            'inventory_category_id' => $category->id,
            'asset_tag' => 'AST-0001',
            'name' => 'Padlock',
            'description' => 'Chao long',
            'status' => 'available',
            'quantity' => '10',
            'unit' => 'pieces',
            'location' => '',
            'assigned_to_user_id' => (string) $assignedUser->id,
            'metadata' => '',
            'purchased_at' => '2026-02-08',
            'warranty_expires_at' => '',
            'serial_number' => 'SN-3001',
        ], $tenant, $actor);

        $item->refresh();

        $this->assertEquals($item->id, $importedItem->id);
        $this->assertEquals('Padlock', $item->name);
        $this->assertEquals(10, $item->quantity);
        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertDatabaseHas('inventory_item_serial_numbers', [
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-3001',
            'assigned_to_user_id' => $assignedUser->id,
        ]);
    }

    public function test_import_creates_missing_category_from_category_columns(): void
    {
        $actor = User::factory()->create();
        $tenant = Department::factory()->create();

        $item = app(InventoryItemCsvImporter::class)->import([
            'inventory_category_id' => '',
            'category_name' => 'Diagnostic Equipment',
            'category_type' => 'asset',
            'parent_category_name' => 'Biomedical',
            'parent_category_type' => 'asset',
            'asset_tag' => 'AST-4001',
            'name' => 'ECG Machine',
            'description' => '',
            'status' => 'available',
            'quantity' => '1',
            'unit' => 'unit',
            'location' => '',
            'assigned_to_user_id' => '',
            'metadata' => '',
            'purchased_at' => '',
            'warranty_expires_at' => '',
            'serial_number' => 'SN-4001',
        ], $tenant, $actor);

        $item->refresh();

        $this->assertEquals('Diagnostic Equipment', $item->category->name);
        $this->assertEquals('asset', $item->category->type);
        $this->assertEquals($tenant->id, $item->category->department_id);
        $this->assertEquals('Biomedical', $item->category->parent->name);
        $this->assertEquals($tenant->id, $item->category->parent->department_id);
        $this->assertDatabaseHas('inventory_categories', [
            'name' => 'Diagnostic Equipment',
            'type' => 'asset',
            'department_id' => $tenant->id,
            'is_deleted' => false,
        ]);
    }

    public function test_import_creates_missing_category_with_new_category_type(): void
    {
        $actor = User::factory()->create();
        $tenant = Department::factory()->create();

        $item = app(InventoryItemCsvImporter::class)->import([
            'inventory_category_id' => '',
            'category_name' => 'Simulation Equipment',
            'category_type' => 'Training Equipment',
            'parent_category_name' => '',
            'parent_category_type' => '',
            'asset_tag' => 'AST-4004',
            'name' => 'VR Headset',
            'description' => '',
            'status' => 'available',
            'quantity' => '1',
            'unit' => 'unit',
            'location' => '',
            'assigned_to_user_id' => '',
            'metadata' => '',
            'purchased_at' => '',
            'warranty_expires_at' => '',
            'serial_number' => '',
        ], $tenant, $actor);

        $item->refresh();

        $this->assertEquals('training_equipment', $item->category->type);
        $this->assertDatabaseHas('inventory_categories', [
            'department_id' => $tenant->id,
            'name' => 'Simulation Equipment',
            'type' => 'training_equipment',
        ]);
    }

    public function test_import_rejects_category_from_another_department(): void
    {
        $actor = User::factory()->create();
        $tenant = Department::factory()->create();
        $otherTenant = Department::factory()->create();
        $category = InventoryCategory::factory()->create([
            'department_id' => $otherTenant->id,
        ]);

        $this->expectException(ValidationException::class);

        app(InventoryItemCsvImporter::class)->import([
            'inventory_category_id' => $category->id,
            'category_name' => '',
            'category_type' => '',
            'asset_tag' => 'AST-4003',
            'name' => 'Wrong Department Item',
            'description' => '',
            'status' => 'available',
            'quantity' => '1',
            'unit' => '',
            'location' => '',
            'assigned_to_user_id' => '',
            'metadata' => '',
            'purchased_at' => '',
            'warranty_expires_at' => '',
            'serial_number' => '',
        ], $tenant, $actor);
    }

    public function test_import_requires_category_name_when_category_id_is_unknown(): void
    {
        $actor = User::factory()->create();
        $tenant = Department::factory()->create();

        $this->expectException(ValidationException::class);

        app(InventoryItemCsvImporter::class)->import([
            'inventory_category_id' => '999',
            'category_name' => '',
            'category_type' => '',
            'asset_tag' => 'AST-4002',
            'name' => 'Unknown Category Item',
            'description' => '',
            'status' => 'available',
            'quantity' => '1',
            'unit' => '',
            'location' => '',
            'assigned_to_user_id' => '',
            'metadata' => '',
            'purchased_at' => '',
            'warranty_expires_at' => '',
            'serial_number' => '',
        ], $tenant, $actor);
    }

    public function test_queued_import_job_imports_csv_rows(): void
    {
        $actor = User::factory()->create();
        $tenant = Department::factory()->create();

        $job = new ImportInventoryItemsFromCsv([
            [
                'inventory_category_id' => '',
                'category_name' => 'Queued Assets',
                'category_type' => 'asset',
                'parent_category_name' => '',
                'parent_category_type' => '',
                'asset_tag' => 'AST-QUEUE-1',
                'name' => 'Queued Laptop',
                'description' => '',
                'status' => 'available',
                'quantity' => '1',
                'unit' => 'unit',
                'location' => 'IT Office',
                'assigned_to_user_id' => '',
                'metadata' => '',
                'purchased_at' => '',
                'warranty_expires_at' => '',
                'serial_number' => 'SN-QUEUE-1',
            ],
        ], $tenant->id, $actor->id);

        $job->handle(app(InventoryItemCsvImporter::class));

        $this->assertDatabaseHas('inventory_items', [
            'asset_tag' => 'AST-QUEUE-1',
            'name' => 'Queued Laptop',
        ]);
        $this->assertDatabaseHas('inventory_categories', [
            'name' => 'Queued Assets',
            'type' => 'asset',
        ]);
        $this->assertDatabaseHas('inventory_item_serial_numbers', [
            'serial_number' => 'SN-QUEUE-1',
        ]);
    }

    public function test_queued_import_job_groups_repeated_asset_tag_rows_under_one_item(): void
    {
        $actor = User::factory()->create();
        $tenant = Department::factory()->create();
        $category = InventoryCategory::factory()->create([
            'department_id' => $tenant->id,
        ]);

        $job = new ImportInventoryItemsFromCsv([
            [
                'inventory_category_id' => $category->id,
                'asset_tag' => 'AST-GROUPED',
                'name' => 'Grouped Laptop',
                'description' => '',
                'status' => 'available',
                'quantity' => '1',
                'unit' => 'unit',
                'location' => '',
                'assigned_to_user_id' => '',
                'metadata' => '',
                'purchased_at' => '',
                'warranty_expires_at' => '',
                'serial_number' => 'SN-GROUPED-1',
            ],
            [
                'inventory_category_id' => $category->id,
                'asset_tag' => 'AST-GROUPED',
                'name' => 'Grouped Laptop',
                'description' => '',
                'status' => 'available',
                'quantity' => '1',
                'unit' => 'unit',
                'location' => '',
                'assigned_to_user_id' => '',
                'metadata' => '',
                'purchased_at' => '',
                'warranty_expires_at' => '',
                'serial_number' => 'SN-GROUPED-2',
            ],
        ], $tenant->id, $actor->id);

        $job->handle(app(InventoryItemCsvImporter::class));

        $item = InventoryItem::query()->where('asset_tag', 'AST-GROUPED')->firstOrFail();

        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(
            ['SN-GROUPED-1', 'SN-GROUPED-2'],
            $item->serialNumbers()->orderBy('serial_number')->pluck('serial_number')->all(),
        );
    }

    public function test_queued_import_job_skips_duplicate_active_serial_and_continues_importing(): void
    {
        $actor = User::factory()->create();
        $tenant = Department::factory()->create();
        $category = InventoryCategory::factory()->create([
            'department_id' => $tenant->id,
        ]);
        $existingItem = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'department_id' => $tenant->id,
        ]);
        InventoryItemSerialNumber::create([
            'inventory_item_id' => $existingItem->id,
            'serial_number' => 'SN-DUPLICATE',
            'status' => 'available',
        ]);

        $job = new ImportInventoryItemsFromCsv([
            [
                'inventory_category_id' => $category->id,
                'asset_tag' => 'AST-BAD-ROW',
                'name' => 'Duplicate Serial Item',
                'description' => '',
                'status' => 'available',
                'quantity' => '1',
                'unit' => '',
                'location' => '',
                'assigned_to_user_id' => '',
                'metadata' => '',
                'purchased_at' => '',
                'warranty_expires_at' => '',
                'serial_number' => 'SN-DUPLICATE',
            ],
            [
                'inventory_category_id' => $category->id,
                'asset_tag' => 'AST-GOOD-ROW',
                'name' => 'Good Import Item',
                'description' => '',
                'status' => 'available',
                'quantity' => '1',
                'unit' => '',
                'location' => '',
                'assigned_to_user_id' => '',
                'metadata' => '',
                'purchased_at' => '',
                'warranty_expires_at' => '',
                'serial_number' => 'SN-GOOD-ROW',
            ],
        ], $tenant->id, $actor->id);

        $job->handle(app(InventoryItemCsvImporter::class));

        $this->assertDatabaseHas('inventory_items', [
            'asset_tag' => 'AST-BAD-ROW',
            'name' => 'Duplicate Serial Item',
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'asset_tag' => 'AST-GOOD-ROW',
            'name' => 'Good Import Item',
        ]);
        $this->assertDatabaseHas('inventory_item_serial_numbers', [
            'inventory_item_id' => $existingItem->id,
            'serial_number' => 'SN-DUPLICATE',
        ]);
        $this->assertDatabaseHas('inventory_item_serial_numbers', [
            'serial_number' => 'SN-GOOD-ROW',
        ]);
    }

    public function test_import_does_not_move_serial_number_from_another_item(): void
    {
        $actor = User::factory()->create();
        $tenant = Department::factory()->create();
        $category = InventoryCategory::factory()->create();
        $existingItem = InventoryItem::factory()->create(['inventory_category_id' => $category->id]);
        InventoryItemSerialNumber::create([
            'inventory_item_id' => $existingItem->id,
            'serial_number' => 'SN-DUPLICATE',
            'status' => 'available',
        ]);

        $item = app(InventoryItemCsvImporter::class)->import([
            'inventory_category_id' => $category->id,
            'asset_tag' => 'AST-NEW',
            'name' => 'New Item',
            'description' => '',
            'status' => 'available',
            'quantity' => '1',
            'unit' => '',
            'location' => '',
            'assigned_to_user_id' => '',
            'metadata' => '',
            'purchased_at' => '',
            'warranty_expires_at' => '',
            'serial_number' => 'SN-DUPLICATE',
        ], $tenant, $actor);

        $this->assertEquals(0, $item->serialNumbers()->count());
        $this->assertDatabaseHas('inventory_item_serial_numbers', [
            'inventory_item_id' => $existingItem->id,
            'serial_number' => 'SN-DUPLICATE',
        ]);
    }

    public function test_import_moves_serial_number_from_deleted_item_to_imported_item(): void
    {
        $actor = User::factory()->create();
        $tenant = Department::factory()->create();
        $category = InventoryCategory::factory()->create();
        $deletedItem = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'is_deleted' => true,
        ]);
        InventoryItemSerialNumber::create([
            'inventory_item_id' => $deletedItem->id,
            'serial_number' => 'SN-DELETED-DUPLICATE',
            'status' => 'available',
        ]);

        $item = app(InventoryItemCsvImporter::class)->import([
            'inventory_category_id' => $category->id,
            'asset_tag' => 'AST-RESTORED-SERIAL',
            'name' => 'Restored Serial Item',
            'description' => '',
            'status' => 'available',
            'quantity' => '1',
            'unit' => '',
            'location' => '',
            'assigned_to_user_id' => '',
            'metadata' => '',
            'purchased_at' => '',
            'warranty_expires_at' => '',
            'serial_number' => 'SN-DELETED-DUPLICATE',
        ], $tenant, $actor);

        $this->assertEquals(1, $item->serialNumbers()->count());
        $this->assertDatabaseHas('inventory_item_serial_numbers', [
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-DELETED-DUPLICATE',
        ]);
    }
}
