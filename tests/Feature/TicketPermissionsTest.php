<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'view_any_ticket', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_ticket', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create_ticket', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'update_ticket', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete_ticket', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'technical_support', 'guard_name' => 'web']);
    }

    public function test_user_with_view_any_permission_can_view_any_tickets(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_any_ticket');

        $this->assertTrue($user->can('viewAny', Ticket::class));
    }

    public function test_user_without_view_any_permission_cannot_view_any_tickets(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('viewAny', Ticket::class));
    }

    public function test_user_with_create_permission_can_create_tickets(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create_ticket');

        $this->assertTrue($user->can('create', Ticket::class));
    }

    public function test_user_with_update_permission_can_update_tickets(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('update_ticket');
        $ticket = Ticket::factory()->create();

        $this->assertTrue($user->can('update', $ticket));
    }

    public function test_user_with_update_permission_can_start_progress_on_active_ticket(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('update_ticket');
        $user->assignRole('technical_support');
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Active]);
        $ticket->technicalSupportUsers()->attach($user);

        $response = $user->can('startProgress', $ticket);

        $this->assertTrue($response);
    }

    public function test_user_with_update_permission_can_start_progress_on_pending_ticket(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('update_ticket');
        $user->assignRole('technical_support');
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Pending]);
        $ticket->technicalSupportUsers()->attach($user);

        $response = $user->can('startProgress', $ticket);

        $this->assertTrue($response);
    }

    public function test_user_cannot_start_progress_on_non_active_ticket(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('update_ticket');
        $user->assignRole('technical_support');
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Closed]);

        $response = $user->can('startProgress', $ticket);

        $this->assertFalse($response);
    }

    public function test_user_with_update_permission_can_close_ticket(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('update_ticket');
        $user->assignRole('technical_support');
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::OnProgress,
            'technical_support_remarks' => 'Resolved by replacing the network cable.',
        ]);
        $ticket->technicalSupportUsers()->attach($user);

        $response = $user->can('close', $ticket);

        $this->assertTrue($response);
    }

    public function test_user_with_update_permission_can_attempt_to_close_pending_ticket_without_existing_remarks(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('update_ticket');
        $user->assignRole('technical_support');
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Pending]);
        $ticket->technicalSupportUsers()->attach($user);

        $response = $user->can('close', $ticket);

        $this->assertTrue($response);
    }

    public function test_unassigned_technical_support_cannot_start_progress(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('update_ticket');
        $user->assignRole('technical_support');
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Active]);

        $response = $user->can('startProgress', $ticket);

        $this->assertFalse($response);
    }

    public function test_super_admin_cannot_start_progress_unless_they_are_assigned_technical_support(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('update_ticket');
        $user->assignRole('super_admin');
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Active]);
        $ticket->technicalSupportUsers()->attach($user);

        $response = $user->can('startProgress', $ticket);

        $this->assertFalse($response);
    }

    public function test_user_without_update_permission_cannot_close_ticket(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Active]);

        $response = $user->can('close', $ticket);

        $this->assertFalse($response);
    }

    public function test_client_can_view_own_ticket(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_ticket');
        $user->assignRole('client');
        $ticket = Ticket::factory()->create(['client_id' => $user->id]);

        $this->assertTrue($user->can('view', $ticket));
    }

    public function test_client_cannot_view_another_client_ticket(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_ticket');
        $user->assignRole('client');
        $ticket = Ticket::factory()->create(['client_id' => User::factory()->create()->id]);

        $this->assertFalse($user->can('view', $ticket));
    }

    public function test_client_cannot_delete_own_ticket(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('delete_ticket');
        $user->assignRole('client');
        $ticket = Ticket::factory()->create(['client_id' => $user->id]);

        $this->assertFalse($user->can('delete', $ticket));
    }
}
