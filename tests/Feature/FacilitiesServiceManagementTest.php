<?php

namespace Tests\Feature;

use App\AssetWorkOrderCreationService;
use App\Filament\Pages\LocationPmsDashboard;
use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\JobOrders\Pages\ListJobOrders;
use App\Filament\Resources\PmsChecklistTemplates\Pages\ListPmsChecklistTemplates;
use App\Filament\Resources\PreventiveMaintenanceAssetChecks\Pages\ListPreventiveMaintenanceAssetChecks;
use App\Filament\Resources\PreventiveMaintenanceAssetChecks\PreventiveMaintenanceAssetCheckResource;
use App\Filament\Resources\PreventiveMaintenanceLogs\Pages\ListPreventiveMaintenanceLogs;
use App\Filament\Resources\PreventiveMaintenanceLogs\PreventiveMaintenanceLogResource;
use App\Filament\Resources\PreventiveMaintenanceSchedules\Pages\ListPreventiveMaintenanceSchedules;
use App\Filament\Resources\PreventiveMaintenanceSessions\Pages\ListPreventiveMaintenanceSessions;
use App\Filament\Resources\PreventiveMaintenanceSessions\PreventiveMaintenanceSessionResource;
use App\Filament\Widgets\FacilitiesServiceStats;
use App\JobOrderCreationService;
use App\JobOrderPdfReport;
use App\JobOrderStatus;
use App\Models\Department;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\JobOrder;
use App\Models\Location;
use App\Models\PmsChecklistItem;
use App\Models\PmsChecklistTemplate;
use App\Models\PreventiveMaintenanceSchedule;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewJobOrderCreated;
use App\Notifications\PmsRepairNeeded;
use App\PmsInspectionService;
use App\PreventiveMaintenanceAssetCheckStatus;
use App\PreventiveMaintenanceGenerationService;
use App\PreventiveMaintenanceLogStatus;
use App\PreventiveMaintenancePdfReport;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class FacilitiesServiceManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShieldSeeder::class);
    }

    public function test_inventory_item_inherits_category_it_classification_by_default_and_can_override_it(): void
    {
        $category = InventoryCategory::factory()->create(['is_it_asset' => true]);
        $inherited = InventoryItem::create(['inventory_category_id' => $category->id, 'name' => 'Inherited Computer', 'status' => 'available', 'quantity' => 1]);
        $overridden = InventoryItem::factory()->create(['inventory_category_id' => $category->id, 'is_it_asset' => false]);

        $this->assertTrue($inherited->is_it_asset);
        $this->assertFalse($overridden->is_it_asset);
    }

    public function test_it_asset_work_request_routes_to_ticket(): void
    {
        [$department, $actor, $item, $serial] = $this->assetContext(true);
        $this->actingAs($actor);

        $work = app(AssetWorkOrderCreationService::class)->create($item, ['subject' => 'Network issue', 'description' => 'No connectivity', 'client_id' => $actor->id, 'inventory_item_serial_number_id' => $serial->id], $actor);

        $this->assertInstanceOf(Ticket::class, $work);
        $this->assertSame($department->id, $work->department_id);
        $this->assertSame($serial->id, $work->inventory_item_serial_number_id);
    }

    public function test_non_it_asset_work_request_routes_to_job_order(): void
    {
        [$department, $actor, $item, $serial] = $this->assetContext(false);
        $this->actingAs($actor);

        $work = app(AssetWorkOrderCreationService::class)->create($item, ['subject' => 'Broken chair', 'description' => 'Chair leg is loose', 'client_id' => $actor->id, 'inventory_item_serial_number_id' => $serial->id], $actor);

        $this->assertInstanceOf(JobOrder::class, $work);
        $this->assertSame('inventory', $work->source);
        $this->assertSame($department->id, $work->department_id);
    }

    public function test_duplicate_open_job_order_for_a_serial_is_rejected(): void
    {
        [, $actor, $item, $serial] = $this->assetContext(false);
        $this->actingAs($actor);
        $service = app(AssetWorkOrderCreationService::class);
        $payload = ['subject' => 'Repair furniture', 'description' => 'Repair required', 'client_id' => $actor->id, 'inventory_item_serial_number_id' => $serial->id];
        $service->create($item, $payload, $actor);

        $this->expectException(ValidationException::class);
        $service->create($item, $payload, $actor);
    }

    public function test_direct_job_order_creation_rejects_it_assets(): void
    {
        [, $actor, $item, $serial] = $this->assetContext(true);

        $this->expectException(ValidationException::class);

        app(JobOrderCreationService::class)->create([
            'subject' => 'Invalid facilities request',
            'description' => 'An IT asset must route to a ticket.',
            'inventory_item_id' => $item->id,
            'inventory_item_serial_number_id' => $serial->id,
        ], $actor);
    }

    public function test_due_it_preventive_maintenance_generates_ticket_log_and_next_due_date(): void
    {
        [$department, $actor, $item, $serial] = $this->assetContext(true);
        $schedule = PreventiveMaintenanceSchedule::factory()->create(['department_id' => $department->id, 'inventory_item_id' => $item->id, 'inventory_item_serial_number_id' => $serial->id, 'created_by' => $actor->id, 'frequency' => 'monthly', 'next_due_at' => now()->subDay()]);

        $log = app(PreventiveMaintenanceGenerationService::class)->generate($schedule);

        $this->assertNotNull($log->ticket_id);
        $this->assertNull($log->job_order_id);
        $this->assertSame(PreventiveMaintenanceLogStatus::Generated, $log->status);
        $this->assertTrue($schedule->fresh()->next_due_at->isFuture());
    }

    public function test_due_non_it_preventive_maintenance_generates_job_order(): void
    {
        [$department, $actor, $item, $serial] = $this->assetContext(false);
        $schedule = PreventiveMaintenanceSchedule::factory()->create(['department_id' => $department->id, 'inventory_item_id' => $item->id, 'inventory_item_serial_number_id' => $serial->id, 'created_by' => $actor->id, 'next_due_at' => now()->subDay()]);

        $log = app(PreventiveMaintenanceGenerationService::class)->generate($schedule);

        $this->assertNotNull($log->job_order_id);
        $this->assertNull($log->ticket_id);
    }

    public function test_preventive_maintenance_generation_skips_when_open_generated_work_exists(): void
    {
        [$department, $actor, $item, $serial] = $this->assetContext(false);
        $schedule = PreventiveMaintenanceSchedule::factory()->create(['department_id' => $department->id, 'inventory_item_id' => $item->id, 'inventory_item_serial_number_id' => $serial->id, 'created_by' => $actor->id, 'next_due_at' => now()->subDays(2)]);
        app(PreventiveMaintenanceGenerationService::class)->generate($schedule);
        $schedule->update(['next_due_at' => now()->subDay()]);

        $log = app(PreventiveMaintenanceGenerationService::class)->generate($schedule);

        $this->assertSame(PreventiveMaintenanceLogStatus::Skipped, $log->status);
        $this->assertSame(1, JobOrder::query()->where('inventory_item_serial_number_id', $serial->id)->count());
    }

    public function test_pms_session_loads_only_active_it_asset_serials_for_the_location(): void
    {
        [$department, $actor, $itItem, $itSerial, $location] = $this->assetContext(true, true);
        $nonItItem = InventoryItem::factory()->create(['department_id' => $department->id, 'is_it_asset' => false]);
        InventoryItemSerialNumber::factory()->for($nonItItem)->create(['location_id' => $location->id]);
        $retiredItem = InventoryItem::factory()->itAsset()->create(['department_id' => $department->id]);
        InventoryItemSerialNumber::factory()->for($retiredItem)->create(['location_id' => $location->id, 'status' => 'retired']);
        $template = $this->checklistTemplate($actor);

        $session = app(PmsInspectionService::class)->startSession($location, $actor, $template);

        $this->assertCount(1, $session->assetChecks);
        $this->assertSame($itSerial->id, $session->assetChecks->first()->inventory_item_serial_number_id);
    }

    public function test_duplicate_active_pms_session_for_location_is_rejected(): void
    {
        [, $actor, , , $location] = $this->assetContext(true, true);
        $template = $this->checklistTemplate($actor);
        app(PmsInspectionService::class)->startSession($location, $actor, $template);

        $this->expectException(ValidationException::class);
        app(PmsInspectionService::class)->startSession($location, $actor, $template);
    }

    public function test_pms_checklist_completion_saves_results_and_history(): void
    {
        [, $actor, , $serial, $location] = $this->assetContext(true, true);
        $template = $this->checklistTemplate($actor);
        $item = $template->items->first();
        $session = app(PmsInspectionService::class)->startSession($location, $actor, $template);
        $check = $session->assetChecks->first();

        $completed = app(PmsInspectionService::class)->completeInspection($check, $actor, PreventiveMaintenanceAssetCheckStatus::Passed, [$item->id => 'pass'], 'All checks passed');

        $this->assertSame(PreventiveMaintenanceAssetCheckStatus::Passed, $completed->status);
        $this->assertSame('pass', $completed->results->first()->value);
        $this->assertSame($completed->id, $serial->latestPreventiveMaintenanceAssetCheck()->value('id'));
        $this->assertNotNull($session->fresh()->completed_at);
    }

    public function test_needs_repair_inspection_can_create_linked_helpdesk_ticket(): void
    {
        [$department, $actor, , , $location] = $this->assetContext(true, true);
        $template = $this->checklistTemplate($actor);
        $item = $template->items->first();
        $session = app(PmsInspectionService::class)->startSession($location, $actor, $template);
        $check = app(PmsInspectionService::class)->completeInspection($session->assetChecks->first(), $actor, PreventiveMaintenanceAssetCheckStatus::NeedsRepair, [$item->id => 'fail'], 'Power supply failed');
        $this->actingAs($actor);

        $ticket = app(PmsInspectionService::class)->createRepairTicket($check, $actor);

        $this->assertSame($department->id, $ticket->department_id);
        $this->assertSame($ticket->id, $check->fresh()->ticket_id);
    }

    public function test_job_order_and_pms_notifications_are_delivered_to_required_roles(): void
    {
        Notification::fake();
        $department = Department::factory()->create();
        $creator = User::factory()->create(['department_id' => $department->id]);
        $creator->assignRole('admin');
        $manager = User::factory()->create();
        $manager->assignRole('job_order_manager');
        $manager->departments()->attach($department, ['is_deleted' => 0]);
        $maintenance = User::factory()->create();
        $maintenance->assignRole('maintenance_staff');
        $maintenance->departments()->attach($department, ['is_deleted' => 0]);
        $otherDepartment = Department::factory()->create();
        $outsideManager = User::factory()->create();
        $outsideManager->assignRole('job_order_manager');
        $outsideManager->departments()->attach($otherDepartment, ['is_deleted' => 0]);
        $technical = User::factory()->create();
        $technical->assignRole('technical_support');
        JobOrder::factory()->create(['department_id' => $department->id, 'created_by' => $creator->id, 'client_id' => $creator->id]);
        Notification::assertSentTo($manager, NewJobOrderCreated::class);
        Notification::assertSentTo($maintenance, NewJobOrderCreated::class);
        Notification::assertNotSentTo($outsideManager, NewJobOrderCreated::class);

        [$pmsDepartment, $pmsCreator, , , $location] = $this->assetContext(true, true);
        $technical->departments()->attach($pmsDepartment, ['is_deleted' => 0]);
        $template = $this->checklistTemplate($pmsCreator);
        $session = app(PmsInspectionService::class)->startSession($location, $pmsCreator, $template);
        $checklistItem = $template->items->first();
        app(PmsInspectionService::class)->completeInspection($session->assetChecks->first(), $pmsCreator, PreventiveMaintenanceAssetCheckStatus::NeedsRepair, [$checklistItem->id => 'fail']);
        Notification::assertSentTo($technical, PmsRepairNeeded::class);
    }

    public function test_job_order_policy_enforces_department_tenancy_and_roles(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $manager = User::factory()->create(['department_id' => $department->id]);
        $manager->assignRole('job_order_manager');
        $manager->departments()->attach($department, ['is_deleted' => 0]);
        $jobOrder = JobOrder::factory()->create(['department_id' => $otherDepartment->id]);

        $this->assertFalse($manager->can('view', $jobOrder));
        $this->assertTrue($manager->can('viewAny', JobOrder::class));
        $this->actingAs($manager);
        $this->assertTrue(JobOrderResource::canViewAny());
        $this->assertFalse(PreventiveMaintenanceSessionResource::canViewAny());
    }

    public function test_reports_apply_department_and_result_filters(): void
    {
        [$department, $actor, $item, $serial, $location] = $this->assetContext(true, true);
        $template = $this->checklistTemplate($actor);
        $session = app(PmsInspectionService::class)->startSession($location, $actor, $template);
        $checklistItem = $template->items->first();
        app(PmsInspectionService::class)->completeInspection($session->assetChecks->first(), $actor, PreventiveMaintenanceAssetCheckStatus::Passed, [$checklistItem->id => 'pass']);
        JobOrder::factory()->create(['department_id' => $department->id, 'created_by' => $actor->id, 'client_id' => $actor->id, 'priority' => 'critical']);

        $this->assertSame(1, JobOrderPdfReport::query(['department_id' => $department->id, 'priority' => ['critical']], $actor)->count());
        $this->assertSame(1, PreventiveMaintenancePdfReport::query(['department_id' => $department->id, 'status' => ['passed']], $actor)->count());
        $this->assertSame(100, PreventiveMaintenancePdfReport::metrics(['department_id' => $department->id], $actor)['compliance']);
    }

    public function test_completed_pms_inspection_cannot_be_submitted_again(): void
    {
        [, $actor, , , $location] = $this->assetContext(true, true);
        $template = $this->checklistTemplate($actor);
        $item = $template->items->first();
        $session = app(PmsInspectionService::class)->startSession($location, $actor, $template);
        $check = $session->assetChecks->first();
        app(PmsInspectionService::class)->completeInspection($check, $actor, PreventiveMaintenanceAssetCheckStatus::Passed, [$item->id => 'pass']);

        $this->expectException(ValidationException::class);
        app(PmsInspectionService::class)->completeInspection($check, $actor, PreventiveMaintenanceAssetCheckStatus::Failed, [$item->id => 'fail']);
    }

    public function test_pms_service_rejects_users_without_department_access(): void
    {
        [, , , , $location] = $this->assetContext(true, true);
        $technicalSupport = User::factory()->create();
        $technicalSupport->assignRole('technical_support');
        $template = $this->checklistTemplate($technicalSupport);

        $this->expectException(AuthorizationException::class);
        app(PmsInspectionService::class)->startSession($location, $technicalSupport, $template);
    }

    public function test_facilities_report_routes_enforce_role_restrictions(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)->get(route('reports.job-orders.pdf'))->assertForbidden();
        $this->actingAs($client)->get(route('reports.preventive-maintenance.pdf'))->assertForbidden();
    }

    public function test_technical_support_can_create_pms_repair_tickets(): void
    {
        $technicalSupport = User::factory()->create();
        $technicalSupport->assignRole('technical_support');

        $this->assertTrue($technicalSupport->can('create_ticket'));
    }

    public function test_scheduled_command_generates_due_preventive_maintenance_work(): void
    {
        [$department, $actor, $item, $serial] = $this->assetContext(false);
        $schedule = PreventiveMaintenanceSchedule::factory()->create([
            'department_id' => $department->id,
            'inventory_item_id' => $item->id,
            'inventory_item_serial_number_id' => $serial->id,
            'created_by' => $actor->id,
            'next_due_at' => now()->subMinute(),
        ]);

        $this->artisan('maintenance:generate-preventive-work')->assertSuccessful();

        $this->assertTrue($schedule->logs()->where('status', PreventiveMaintenanceLogStatus::Generated->value)->exists());
    }

    public function test_applied_pms_schema_enforces_required_relationships_and_session_serial_uniqueness(): void
    {
        $scheduleForeignKeyColumns = collect(Schema::getForeignKeys('preventive_maintenance_schedules'))->pluck('columns')->flatten()->all();
        $assetCheckForeignKeyColumns = collect(Schema::getForeignKeys('preventive_maintenance_asset_checks'))->pluck('columns')->flatten()->all();
        $assetCheckIndexes = collect(Schema::getIndexes('preventive_maintenance_asset_checks'));

        $this->assertContains('inventory_item_serial_number_id', $scheduleForeignKeyColumns);
        $this->assertContains('assigned_to_user_id', $scheduleForeignKeyColumns);
        $this->assertContains('created_by', $scheduleForeignKeyColumns);
        $this->assertContains('inventory_item_serial_number_id', $assetCheckForeignKeyColumns);
        $this->assertContains('checked_by', $assetCheckForeignKeyColumns);
        $this->assertContains('checklist_template_id', $assetCheckForeignKeyColumns);
        $this->assertContains('ticket_id', $assetCheckForeignKeyColumns);
        $this->assertTrue($assetCheckIndexes->contains(fn (array $index): bool => $index['columns'] === ['session_id', 'inventory_item_serial_number_id'] && $index['unique']));
    }

    public function test_dashboard_statistics_render_for_current_department(): void
    {
        [$department, $actor] = $this->assetContext(true);
        $this->actingAs($actor);
        JobOrder::factory()->create(['department_id' => $department->id, 'created_by' => $actor->id, 'client_id' => $actor->id]);
        Filament::setTenant($department);
        $widget = app(FacilitiesServiceStats::class);
        $method = new ReflectionMethod($widget, 'getStats');
        $stats = $method->invoke($widget);

        $this->assertCount(7, $stats);
    }

    public function test_facilities_filament_resource_pages_render_for_authorized_tenant_user(): void
    {
        [$department, $actor] = $this->assetContext(true);
        Filament::setTenant($department, isQuiet: true);

        foreach ([
            ListPreventiveMaintenanceSchedules::class,
            ListPreventiveMaintenanceLogs::class,
            ListPmsChecklistTemplates::class,
            ListPreventiveMaintenanceSessions::class,
            ListPreventiveMaintenanceAssetChecks::class,
            ListJobOrders::class,
            LocationPmsDashboard::class,
        ] as $page) {
            Livewire::actingAs($actor)->test($page)->assertOk();
        }
    }

    public function test_preventive_maintenance_log_and_asset_check_resources_are_read_only(): void
    {
        $this->assertFalse(PreventiveMaintenanceLogResource::canCreate());
        $this->assertFalse(PreventiveMaintenanceAssetCheckResource::canCreate());
    }

    public function test_job_order_closed_status_can_be_reopened(): void
    {
        $jobOrder = JobOrder::factory()->create(['status' => 'closed', 'completed_at' => now()]);

        $this->assertTrue($jobOrder->transitionTo(JobOrderStatus::Active));
        $this->assertSame(JobOrderStatus::Active, $jobOrder->fresh()->status);
        $this->assertNull($jobOrder->fresh()->completed_at);
    }

    private function checklistTemplate(User $creator): PmsChecklistTemplate
    {
        $template = PmsChecklistTemplate::factory()->create(['created_by' => $creator->id]);
        PmsChecklistItem::factory()->create(['template_id' => $template->id, 'label' => 'Power-on test', 'input_type' => 'pass_fail', 'is_required' => true]);

        return $template->load('items');
    }

    private function assetContext(bool $isItAsset, bool $withLocation = false): array
    {
        $department = Department::factory()->create();
        $actor = User::factory()->create(['department_id' => $department->id]);
        $actor->assignRole('super_admin');
        $actor->departments()->attach($department, ['is_deleted' => 0]);
        $category = InventoryCategory::factory()->create(['department_id' => $department->id, 'is_it_asset' => $isItAsset]);
        $item = InventoryItem::factory()->create(['inventory_category_id' => $category->id, 'department_id' => $department->id, 'is_it_asset' => $isItAsset]);
        $location = $withLocation ? Location::factory()->create(['department_id' => $department->id]) : null;
        $serial = InventoryItemSerialNumber::factory()->for($item)->create(['location_id' => $location?->id]);

        return [$department, $actor, $item, $serial, $location];
    }
}
