<?php

namespace Tests\Feature;

use App\Filament\Pages\TicketReports;
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

    public function test_technical_support_can_report_all_departments(): void
    {
        PermissionRole::firstOrCreate(['name' => 'technical_support', 'guard_name' => 'web']);
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $user = User::factory()->create([
            'department_id' => $department->id,
            'status' => 1,
            'is_deleted' => 0,
        ]);
        $user->assignRole('technical_support');
        $user->departments()->attach($department);

        $firstTicket = Ticket::factory()->create(['department_id' => $department->id]);
        $secondTicket = Ticket::factory()->create(['department_id' => $otherDepartment->id]);

        $tickets = TicketPdfReport::query([
            'department_id' => 'all',
        ], $user)->get();

        $this->assertCount(2, $tickets);
        $this->assertTrue($tickets->contains($firstTicket));
        $this->assertTrue($tickets->contains($secondTicket));
    }

    public function test_admin_cannot_use_all_departments_report_filter(): void
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
            'department_id' => 'all',
        ], $user)->get();

        $this->assertCount(1, $tickets);
        $this->assertTrue($tickets->contains($included));
    }

    public function test_report_department_options_include_all_departments_for_super_admin_and_technical_support(): void
    {
        PermissionRole::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        PermissionRole::firstOrCreate(['name' => 'technical_support', 'guard_name' => 'web']);
        PermissionRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();

        $superAdmin = User::factory()->create(['department_id' => $department->id, 'status' => 1, 'is_deleted' => 0]);
        $superAdmin->assignRole('super_admin');

        $technicalSupport = User::factory()->create(['department_id' => $department->id, 'status' => 1, 'is_deleted' => 0]);
        $technicalSupport->assignRole('technical_support');
        $technicalSupport->departments()->attach($department);

        $admin = User::factory()->create(['department_id' => $department->id, 'status' => 1, 'is_deleted' => 0]);
        $admin->assignRole('admin');
        $admin->departments()->attach($department);

        $this->actingAs($superAdmin);
        $this->assertSame('All Departments', app(TicketReports::class)->departmentOptions()['all']);
        $this->assertArrayHasKey($otherDepartment->id, app(TicketReports::class)->departmentOptions());

        $this->actingAs($technicalSupport);
        $this->assertSame('All Departments', app(TicketReports::class)->departmentOptions()['all']);
        $this->assertArrayHasKey($otherDepartment->id, app(TicketReports::class)->departmentOptions());

        $this->actingAs($admin);
        $this->assertArrayNotHasKey('all', app(TicketReports::class)->departmentOptions());
        $this->assertArrayNotHasKey($otherDepartment->id, app(TicketReports::class)->departmentOptions());
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
