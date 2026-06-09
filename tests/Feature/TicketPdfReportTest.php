<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\TicketPdfReport;
use App\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as PermissionRole;
use Tests\TestCase;

class TicketPdfReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_pdf_report_filters_visible_tickets(): void
    {
        PermissionRole::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $user = User::factory()->create([
            'department_id' => $department->id,
            'status' => 1,
            'is_deleted' => 0,
        ]);
        $user->assignRole('super_admin');

        $included = Ticket::factory()->create([
            'department_id' => $department->id,
            'status' => TicketStatus::Closed,
            'priority' => 'critical',
            'created_at' => '2026-06-01 10:00:00',
        ]);
        Ticket::factory()->create([
            'department_id' => $department->id,
            'status' => TicketStatus::Active,
            'priority' => 'critical',
            'created_at' => '2026-06-01 10:00:00',
        ]);
        Ticket::factory()->create([
            'department_id' => $otherDepartment->id,
            'status' => TicketStatus::Closed,
            'priority' => 'critical',
            'created_at' => '2026-06-01 10:00:00',
        ]);

        $tickets = TicketPdfReport::query([
            'department_id' => $department->id,
            'status' => [TicketStatus::Closed->value],
            'priority' => ['critical'],
            'created_from' => '2026-06-01',
            'created_until' => '2026-06-01',
        ], $user)->get();

        $this->assertCount(1, $tickets);
        $this->assertTrue($tickets->contains($included));
    }

    public function test_non_super_admin_cannot_widen_report_to_another_department(): void
    {
        PermissionRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $user = User::factory()->create([
            'department_id' => $department->id,
            'status' => 1,
            'is_deleted' => 0,
        ]);
        $user->assignRole('admin');
        $user->departments()->attach($department);

        $included = Ticket::factory()->create(['department_id' => $department->id]);
        Ticket::factory()->create(['department_id' => $otherDepartment->id]);

        $tickets = TicketPdfReport::query([
            'department_id' => $otherDepartment->id,
        ], $user)->get();

        $this->assertCount(1, $tickets);
        $this->assertTrue($tickets->contains($included));
    }

    public function test_ticket_pdf_report_route_returns_pdf(): void
    {
        PermissionRole::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $department = Department::factory()->create();
        $user = User::factory()->create([
            'department_id' => $department->id,
            'status' => 1,
            'is_deleted' => 0,
        ]);
        $user->assignRole('super_admin');
        Ticket::factory()->create([
            'department_id' => $department->id,
            'subject' => 'Printer outage',
        ]);

        $response = $this->actingAs($user)->get(route('reports.tickets.pdf', [
            'department_id' => $department->id,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
    }
}
