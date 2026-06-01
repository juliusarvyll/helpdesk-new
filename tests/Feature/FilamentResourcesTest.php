<?php

namespace Tests\Feature;

use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\TicketResource;
use App\Filament\Resources\UserResource;
use App\Filament\Widgets\TicketStatsOverview;
use App\Models\Department;
use App\Models\IssueCategory;
use App\Models\IssueList;
use App\Models\Position;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\TicketStatus;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as PermissionRole;
use Tests\TestCase;

class FilamentResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_with_unit_head_relationship(): void
    {
        $unitHead = User::factory()->create();
        $department = Department::factory()->create(['unit_head' => $unitHead->id]);

        $this->assertInstanceOf(User::class, $department->unitHeadUser);
        $this->assertEquals($unitHead->id, $department->unitHeadUser->id);
    }

    public function test_user_with_all_relationships(): void
    {
        $department = Department::factory()->create();
        $position = Position::factory()->create();
        $role = Role::create(['name' => 'Manager']);

        $user = User::factory()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'role_id' => $role->id,
        ]);

        $this->assertEquals($department->id, $user->department->id);
        $this->assertEquals($position->id, $user->position->id);
        $this->assertEquals($role->id, $user->roleRelation->id);
    }

    public function test_user_resource_uses_assignable_shield_roles(): void
    {
        $this->seed(ShieldSeeder::class);

        $options = UserResource::shieldRoleOptions();

        $this->assertSame(['super_admin', 'admin', 'technical_support', 'client'], array_values($options));
        $this->assertNotContains('panel_user', $options);
    }

    public function test_user_resource_syncs_primary_department_to_tenant_departments(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $department->id]);

        UserResource::syncPrimaryDepartmentTenant($user);

        $this->assertTrue($user->departments()->whereKey($department->id)->exists());
    }

    public function test_user_tenant_departments_query_qualifies_department_deleted_column(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $department->id]);

        UserResource::syncPrimaryDepartmentTenant($user);

        $this->assertEquals(
            [$department->id],
            $user->departments()->where('department.is_deleted', 0)->pluck('department.id')->all(),
        );
    }

    public function test_inventory_item_resource_metadata_is_normalized_for_key_value_field(): void
    {
        $metadata = InventoryItemResource::metadataForKeyValue([
            'source_file' => 'control room.docx',
            'source_files' => ['control room.docx', 'LR101.docx'],
            'functional' => true,
            'empty' => null,
        ]);

        $this->assertSame('control room.docx', $metadata['source_file']);
        $this->assertSame('["control room.docx","LR101.docx"]', $metadata['source_files']);
        $this->assertSame('true', $metadata['functional']);
        $this->assertNull($metadata['empty']);
    }

    public function test_ticket_with_technical_support_users(): void
    {
        $client = User::factory()->create();
        $techSupport1 = User::factory()->create();
        $techSupport2 = User::factory()->create();

        $ticket = Ticket::factory()->create(['client_id' => $client->id]);
        $ticket->technicalSupportUsers()->attach([$techSupport1->id, $techSupport2->id]);

        $this->assertCount(2, $ticket->technicalSupportUsers);
        $this->assertTrue($ticket->technicalSupportUsers->contains($techSupport1));
        $this->assertTrue($ticket->technicalSupportUsers->contains($techSupport2));
        $this->assertTrue($techSupport1->assignedTickets->contains($ticket));
        $this->assertTrue($techSupport2->assignedTickets->contains($ticket));
    }

    public function test_ticket_resource_formats_technical_support_names_as_single_comma_separated_text(): void
    {
        $techSupport1 = User::factory()->create(['name' => 'Juan Dela Cruz']);
        $techSupport2 = User::factory()->create(['name' => 'Maria Santos']);
        $ticket = Ticket::factory()->create();

        $ticket->technicalSupportUsers()->attach([$techSupport1->id, $techSupport2->id]);
        $ticket->load('technicalSupportUsers');

        $this->assertSame('Juan Dela Cruz, Maria Santos', TicketResource::technicalSupportNames($ticket));
    }

    public function test_ticket_resource_formats_unassigned_technical_support_as_unassigned(): void
    {
        $ticket = Ticket::factory()->create();
        $ticket->load('technicalSupportUsers');

        $this->assertSame('Unassigned', TicketResource::technicalSupportNames($ticket));
    }

    public function test_dashboard_ticket_analytics_helpers(): void
    {
        Ticket::factory()->create([
            'status' => TicketStatus::Closed,
            'start_time' => now()->subHours(3),
            'end_time' => now(),
        ]);

        $this->assertSame(50, TicketStatsOverview::percentage(1, 2));
        $this->assertSame(0, TicketStatsOverview::percentage(1, 0));
        $this->assertSame('3h', TicketStatsOverview::formatHours(TicketStatsOverview::averageResolutionHours(Ticket::query())));
        $this->assertSame('2 more than last week', TicketStatsOverview::weeklyChangeDescription(2));
        $this->assertSame('1 fewer than last week', TicketStatsOverview::weeklyChangeDescription(-1));
    }

    public function test_department_ticket_relationship_uses_normalized_department_id(): void
    {
        $department = Department::factory()->create();

        $ticket = $department->tickets()->create([
            'subject' => 'Printer not working',
            'description' => 'The shared printer is offline.',
            'priority' => 'normal',
            'created_ticket' => 'Angelo Peralta',
        ]);

        $this->assertEquals($department->id, $ticket->department_id);
        $this->assertEquals($department->id, $ticket->department->id);
    }

    public function test_client_role_cannot_manage_technical_support_assignments(): void
    {
        PermissionRole::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user);

        $data = TicketResource::sanitizeTechnicalSupportAssignmentData([
            'subject' => 'Cannot print',
            'technicalSupportUsers' => [1, 2],
        ]);

        $this->assertFalse(TicketResource::canManageTechnicalSupportAssignments());
        $this->assertArrayNotHasKey('technicalSupportUsers', $data);
    }

    public function test_technical_support_role_can_manage_technical_support_assignments(): void
    {
        PermissionRole::firstOrCreate(['name' => 'technical_support', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('technical_support');

        $this->actingAs($user);

        $data = TicketResource::sanitizeTechnicalSupportAssignmentData([
            'subject' => 'Cannot print',
            'technicalSupportUsers' => [1, 2],
        ]);

        $this->assertTrue(TicketResource::canManageTechnicalSupportAssignments());
        $this->assertSame([1, 2], $data['technicalSupportUsers']);
    }

    public function test_ticket_create_data_strips_technical_support_assignments(): void
    {
        PermissionRole::firstOrCreate(['name' => 'technical_support', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('technical_support');

        $this->actingAs($user);

        $data = TicketResource::sanitizeTechnicalSupportAssignmentData([
            'subject' => 'Cannot print',
            'technicalSupportUsers' => [1, 2],
        ], allowAssignment: false);

        $this->assertTrue(TicketResource::canManageTechnicalSupportAssignments());
        $this->assertArrayNotHasKey('technicalSupportUsers', $data);
    }

    public function test_direct_ticket_create_data_uses_authenticated_user_and_removes_inventory_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $data = TicketResource::prepareDirectTicketCreateData([
            'subject' => 'Cannot print',
            'description' => 'The printer shows an error.',
            'category' => '1',
            'issue_id' => 2,
            'client_id' => User::factory()->create()->id,
            'priority' => 'critical',
            'inventory_item_id' => 10,
            'inventory_item_serial_number_id' => 20,
            'asset_id' => 'AST-001',
            'asset_name' => 'Printer',
            'client_comments' => 'extra',
            'technicalSupportUsers' => [1, 2],
        ]);

        $this->assertSame($user->id, $data['client_id']);
        $this->assertSame('normal', $data['priority']);
        $this->assertSame(TicketStatus::Active->value, $data['status']);
        $this->assertArrayNotHasKey('inventory_item_id', $data);
        $this->assertArrayNotHasKey('inventory_item_serial_number_id', $data);
        $this->assertArrayNotHasKey('asset_id', $data);
        $this->assertArrayNotHasKey('asset_name', $data);
        $this->assertArrayNotHasKey('client_comments', $data);
        $this->assertArrayNotHasKey('technicalSupportUsers', $data);
    }

    public function test_closed_ticket_does_not_show_status_transition_actions(): void
    {
        Permission::firstOrCreate(['name' => 'update_ticket', 'guard_name' => 'web']);
        PermissionRole::firstOrCreate(['name' => 'technical_support', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('update_ticket');
        $user->assignRole('technical_support');
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Closed]);

        $this->actingAs($user);

        $this->assertFalse(TicketResource::canShowStatusTransitionAction($ticket, 'startProgress'));
        $this->assertFalse(TicketResource::canShowStatusTransitionAction($ticket, 'markPending'));
        $this->assertFalse(TicketResource::canShowStatusTransitionAction($ticket, 'close'));
    }

    public function test_unassigned_ticket_does_not_show_status_transition_actions(): void
    {
        Permission::firstOrCreate(['name' => 'update_ticket', 'guard_name' => 'web']);
        PermissionRole::firstOrCreate(['name' => 'technical_support', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('update_ticket');
        $user->assignRole('technical_support');
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Active]);

        $this->actingAs($user);

        $this->assertFalse(TicketResource::canShowStatusTransitionAction($ticket, 'startProgress'));
        $this->assertFalse(TicketResource::canShowStatusTransitionAction($ticket, 'markPending'));
        $this->assertFalse(TicketResource::canShowStatusTransitionAction($ticket, 'close'));
    }

    public function test_assigned_ticket_can_show_status_transition_actions(): void
    {
        Permission::firstOrCreate(['name' => 'update_ticket', 'guard_name' => 'web']);
        PermissionRole::firstOrCreate(['name' => 'technical_support', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('update_ticket');
        $user->assignRole('technical_support');
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Active]);
        $ticket->technicalSupportUsers()->attach($user);

        $this->actingAs($user);

        $this->assertTrue(TicketResource::canShowStatusTransitionAction($ticket, 'startProgress'));
    }

    public function test_super_admin_and_technical_support_can_assign_unassigned_tickets(): void
    {
        Permission::firstOrCreate(['name' => 'update_ticket', 'guard_name' => 'web']);
        PermissionRole::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        PermissionRole::firstOrCreate(['name' => 'technical_support', 'guard_name' => 'web']);
        PermissionRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Active]);

        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo('update_ticket');
        $superAdmin->assignRole('super_admin');

        $technicalSupport = User::factory()->create();
        $technicalSupport->givePermissionTo('update_ticket');
        $technicalSupport->assignRole('technical_support');

        $admin = User::factory()->create();
        $admin->givePermissionTo('update_ticket');
        $admin->assignRole('admin');

        $this->actingAs($superAdmin);
        $this->assertTrue(TicketResource::canShowAssignTicketAction($ticket));

        $this->actingAs($technicalSupport);
        $this->assertTrue(TicketResource::canShowAssignTicketAction($ticket));

        $this->actingAs($admin);
        $this->assertFalse(TicketResource::canShowAssignTicketAction($ticket));
    }

    public function test_assigning_technical_support_users_marks_ticket_assigned(): void
    {
        Notification::fake();
        PermissionRole::firstOrCreate(['name' => 'technical_support', 'guard_name' => 'web']);
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Active]);
        $technicalSupport = User::factory()->create();
        $technicalSupport->assignRole('technical_support');

        TicketResource::assignTechnicalSupportUsers($ticket, [$technicalSupport->id]);

        $ticket->refresh();

        $this->assertTrue($ticket->technicalSupportUsers()->whereKey($technicalSupport->id)->exists());
        $this->assertSame('Assigned', $ticket->support_assignment_status);
        $this->assertNotNull($ticket->assigned_at);
    }

    public function test_client_role_ticket_create_data_uses_authenticated_user_as_client_and_department(): void
    {
        PermissionRole::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $department->id]);
        $otherUser = User::factory()->create(['department_id' => $otherDepartment->id]);
        $user->assignRole('client');

        $this->actingAs($user);

        $data = TicketResource::assignCreatorClientAndDepartmentData([
            'client_id' => $otherUser->id,
            'department_id' => $otherDepartment->id,
        ]);

        $this->assertSame($user->id, $data['client_id']);
        $this->assertSame($department->id, $data['department_id']);
    }

    public function test_super_admin_ticket_create_data_can_use_selected_client_and_department(): void
    {
        PermissionRole::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $department = Department::factory()->create();
        $client = User::factory()->create(['department_id' => $department->id]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        $data = TicketResource::assignCreatorClientAndDepartmentData([
            'client_id' => $client->id,
        ]);

        $this->assertSame($client->id, $data['client_id']);
        $this->assertSame($department->id, $data['department_id']);
    }

    public function test_technical_support_ticket_create_data_can_use_selected_client_and_department(): void
    {
        PermissionRole::firstOrCreate(['name' => 'technical_support', 'guard_name' => 'web']);
        $technicalSupport = User::factory()->create();
        $department = Department::factory()->create();
        $client = User::factory()->create(['department_id' => $department->id]);
        $technicalSupport->assignRole('technical_support');

        $this->actingAs($technicalSupport);

        $data = TicketResource::assignCreatorClientAndDepartmentData([
            'client_id' => $client->id,
        ]);

        $this->assertTrue(TicketResource::canSelectTicketClient());
        $this->assertSame($client->id, $data['client_id']);
        $this->assertSame($department->id, $data['department_id']);
    }

    public function test_client_ticket_data_strips_internal_fields_but_preserves_create_ownership(): void
    {
        PermissionRole::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user);

        $data = TicketResource::sanitizeClientTicketData([
            'client_id' => $user->id,
            'department_id' => 10,
            'status' => 'closed',
            'technical_support_remarks' => 'internal',
            'client_comments' => 'please update me',
        ], preserveOwnership: true);

        $this->assertSame($user->id, $data['client_id']);
        $this->assertSame(10, $data['department_id']);
        $this->assertSame('please update me', $data['client_comments']);
        $this->assertArrayNotHasKey('status', $data);
        $this->assertArrayNotHasKey('technical_support_remarks', $data);
    }

    public function test_ticket_assignment_state_sets_assigned_timestamp(): void
    {
        $ticket = Ticket::factory()->create();
        $technicalSupport = User::factory()->create();

        $ticket->technicalSupportUsers()->attach($technicalSupport);
        $ticket->syncAssignmentState();

        $ticket->refresh();

        $this->assertEquals('Assigned', $ticket->support_assignment_status);
        $this->assertNotNull($ticket->assigned_at);
    }

    public function test_ticket_with_issue_relationship(): void
    {
        $category = IssueCategory::factory()->create();
        $issue = IssueList::factory()->create(['issue_category_id' => $category->id]);
        $client = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'issue_id' => $issue->id,
            'client_id' => $client->id,
        ]);

        $this->assertEquals($issue->id, $ticket->issue->id);
        $this->assertEquals($category->id, $ticket->issue->category->id);
    }
}
