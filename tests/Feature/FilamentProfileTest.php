<?php

namespace Tests\Feature;

use App\Filament\Pages\MyProfile;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as PermissionRole;
use Tests\TestCase;

class FilamentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_filament_profile_page(): void
    {
        $department = Department::factory()->create();
        $user = $this->panelUser($department);

        $this->actingAs($user)
            ->get('/department/'.$department->getKey().'/my-profile')
            ->assertOk();
    }

    public function test_user_can_update_their_own_password_from_filament_profile(): void
    {
        $department = Department::factory()->create();
        $user = $this->panelUser($department);

        Livewire::actingAs($user)
            ->test(MyProfile::class)
            ->set('data.name', 'Updated Name')
            ->set('data.email', 'updated@example.com')
            ->set('data.current_password', 'password')
            ->set('data.password', 'new-password')
            ->set('data.password_confirmation', 'new-password')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    public function test_current_password_is_required_to_change_filament_profile_password(): void
    {
        $department = Department::factory()->create();
        $user = $this->panelUser($department);

        Livewire::actingAs($user)
            ->test(MyProfile::class)
            ->set('data.current_password', 'wrong-password')
            ->set('data.password', 'new-password')
            ->set('data.password_confirmation', 'new-password')
            ->call('save')
            ->assertHasErrors(['current_password']);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
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
}
