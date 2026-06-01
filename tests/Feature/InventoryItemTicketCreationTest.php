<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\IssueList;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewTicketCreated;
use App\TicketCreationService;
use App\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryItemTicketCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_can_be_created_for_inventory_item_serial_number(): void
    {
        Notification::fake();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $department = Department::factory()->create();
        $actor = User::factory()->create(['department_id' => $department->id]);
        $actor->assignRole('super_admin');
        $client = User::factory()->create(['department_id' => $department->id]);
        $technicalSupport = User::factory()->create(['department_id' => $department->id]);
        $category = InventoryCategory::factory()->create();
        $item = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'asset_tag' => 'AST-TICKET',
            'name' => 'Ticket Laptop',
        ]);
        $serialNumber = InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-TICKET',
            'status' => 'available',
        ]);
        $issue = IssueList::factory()->create();

        $this->actingAs($actor);

        $ticket = app(TicketCreationService::class)->create([
            'subject' => 'Keyboard not working',
            'description' => 'The keyboard intermittently stops responding.',
            'priority' => 'normal',
            'issue_id' => $issue->id,
            'client_id' => $client->id,
            'inventory_item_id' => $item->id,
            'inventory_item_serial_number_id' => $serialNumber->id,
            'asset_id' => $item->asset_tag,
            'asset_name' => $item->name,
            'technicalSupportUsers' => [$technicalSupport->id],
        ], $actor);

        $this->assertEquals($item->id, $ticket->inventory_item_id);
        $this->assertEquals($serialNumber->id, $ticket->inventory_item_serial_number_id);
        $this->assertEquals($department->id, $ticket->department_id);
        $this->assertFalse($ticket->technicalSupportUsers()->whereKey($technicalSupport->id)->exists());
        $this->assertEquals('Not Yet Assigned', $ticket->support_assignment_status);
        Notification::assertSentTo($actor, NewTicketCreated::class);
    }

    public function test_inventory_item_ticket_requires_serial_number(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $department = Department::factory()->create();
        $actor = User::factory()->create(['department_id' => $department->id]);
        $actor->assignRole('super_admin');
        $client = User::factory()->create(['department_id' => $department->id]);
        $category = InventoryCategory::factory()->create();
        $item = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'asset_tag' => 'AST-TICKET-REQUIRED',
            'name' => 'Ticket Laptop',
        ]);
        InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-TICKET-REQUIRED',
            'status' => 'available',
        ]);
        $issue = IssueList::factory()->create();

        $this->actingAs($actor);
        $this->expectException(ValidationException::class);

        app(TicketCreationService::class)->create([
            'subject' => 'Keyboard not working',
            'description' => 'The keyboard intermittently stops responding.',
            'priority' => 'normal',
            'issue_id' => $issue->id,
            'client_id' => $client->id,
            'inventory_item_id' => $item->id,
            'asset_id' => $item->asset_tag,
            'asset_name' => $item->name,
        ], $actor);
    }

    public function test_inventory_item_ticket_rejects_serial_number_with_open_ticket(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $department = Department::factory()->create();
        $actor = User::factory()->create(['department_id' => $department->id]);
        $actor->assignRole('super_admin');
        $client = User::factory()->create(['department_id' => $department->id]);
        $category = InventoryCategory::factory()->create();
        $item = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'asset_tag' => 'AST-TICKET-DUPLICATE',
            'name' => 'Ticket Laptop',
        ]);
        $serialNumber = InventoryItemSerialNumber::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-TICKET-DUPLICATE',
            'status' => 'available',
        ]);
        $issue = IssueList::factory()->create();

        Ticket::factory()->create([
            'inventory_item_id' => $item->id,
            'inventory_item_serial_number_id' => $serialNumber->id,
            'status' => TicketStatus::Active,
        ]);

        $this->actingAs($actor);
        $this->expectException(ValidationException::class);

        app(TicketCreationService::class)->create([
            'subject' => 'Keyboard not working',
            'description' => 'The keyboard intermittently stops responding.',
            'priority' => 'normal',
            'issue_id' => $issue->id,
            'client_id' => $client->id,
            'inventory_item_id' => $item->id,
            'inventory_item_serial_number_id' => $serialNumber->id,
            'asset_id' => $item->asset_tag,
            'asset_name' => $item->name,
        ], $actor);
    }
}
