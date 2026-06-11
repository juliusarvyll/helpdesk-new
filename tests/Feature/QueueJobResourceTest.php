<?php

namespace Tests\Feature;

use App\Filament\Resources\DatabaseNotificationResource;
use App\Filament\Resources\QueueJobResource;
use App\Models\QueueJob;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QueueJobResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_job_model_exposes_status_and_display_name(): void
    {
        DB::table('jobs')->insert([
            'id' => 1,
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ImportMicrosoftUsers']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $job = QueueJob::query()->firstOrFail();

        $this->assertSame('queued', $job->status);
        $this->assertSame('App\\Jobs\\ImportMicrosoftUsers', $job->display_name);
        $this->assertNotNull($job->available_at_date);
    }

    public function test_queue_worker_resource_is_only_visible_to_super_admin(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $admin = $this->userWithRole('admin');

        $this->actingAs($superAdmin);
        $this->assertTrue(QueueJobResource::canViewAny());
        $this->assertFalse(QueueJobResource::canCreate());

        $this->actingAs($admin);
        $this->assertFalse(QueueJobResource::canViewAny());
    }

    public function test_notification_resource_is_hidden_from_navigation_but_panel_database_notifications_are_enabled(): void
    {
        $this->assertFalse(DatabaseNotificationResource::shouldRegisterNavigation());

        $panel = (new AdminPanelProvider(app()))->panel(Panel::make());

        $this->assertTrue($panel->hasDatabaseNotifications());
        $this->assertSame('15s', $panel->getDatabaseNotificationsPollingInterval());
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::findOrCreate($roleName);
        $user = User::factory()->create([
            'status' => 1,
            'is_deleted' => 0,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
