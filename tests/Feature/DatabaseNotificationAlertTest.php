<?php

namespace Tests\Feature;

use App\Filament\Resources\DatabaseNotificationResource;
use App\Filament\Resources\DatabaseNotificationResource\Pages\ListDatabaseNotifications;
use App\Models\Department;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as PermissionRole;
use Tests\TestCase;

class DatabaseNotificationAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_database_notifications_show_a_filament_alert_once_per_unread_signature(): void
    {
        $department = Department::factory()->create();
        $user = $this->panelUser($department);
        $this->databaseNotificationFor($user, ['title' => 'Unread ticket', 'body' => 'Ticket assigned.']);

        $this->actingAs($user)
            ->get('/department/'.$department->getKey().'/database-notifications')
            ->assertOk()
            ->assertSee('You have 1 unread notification.')
            ->assertSessionHas('database_notifications.unread_alert_signature');

        session()->forget('filament.notifications');

        $this->actingAs($user)
            ->get('/department/'.$department->getKey().'/database-notifications')
            ->assertOk()
            ->assertDontSee('You have 1 unread notification.');
    }

    public function test_unread_database_notification_alert_uses_accessible_tenant_on_tenant_redirect_route(): void
    {
        $department = Department::factory()->create();
        $user = $this->panelUser($department);
        $this->databaseNotificationFor($user);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/department/'.$department->getKey());
    }

    public function test_mark_all_as_read_marks_only_the_authenticated_users_notifications(): void
    {
        $department = Department::factory()->create();
        $user = $this->panelUser($department);
        $otherUser = $this->panelUser($department);
        $notification = $this->databaseNotificationFor($user);
        $otherNotification = $this->databaseNotificationFor($otherUser);

        $this->actingAs($user);

        $this->assertSame(1, DatabaseNotificationResource::markAllAsRead());

        $this->assertNotNull($notification->refresh()->read_at);
        $this->assertNull($otherNotification->refresh()->read_at);
    }

    public function test_database_notification_page_can_mark_all_as_read(): void
    {
        $department = Department::factory()->create();
        $user = $this->panelUser($department);
        $notification = $this->databaseNotificationFor($user);

        Filament::setTenant($department, isQuiet: true);

        Livewire::actingAs($user)
            ->test(ListDatabaseNotifications::class)
            ->callAction('markAllAsRead')
            ->assertHasNoActionErrors();

        $this->assertNotNull($notification->refresh()->read_at);
    }

    private function panelUser(Department $department): User
    {
        PermissionRole::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'department_id' => $department->id,
            'status' => 1,
            'is_deleted' => 0,
        ]);
        $user->assignRole('client');
        $user->departments()->attach($department);

        return $user;
    }

    /**
     * @param  array<string, string>  $data
     */
    private function databaseNotificationFor(User $user, array $data = ['title' => 'Test notification', 'body' => 'Please review.']): DatabaseNotification
    {
        return DatabaseNotification::query()->create([
            'id' => fake()->uuid(),
            'type' => 'database',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => $data,
            'read_at' => null,
        ]);
    }
}
