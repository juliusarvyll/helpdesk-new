<?php

namespace Tests\Feature;

use App\Filament\Resources\InventoryCategoryResource;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InventoryTransactionResource;
use App\Models\Role as LegacyRole;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShieldSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_shield_seeder_creates_all_helpdesk_roles(): void
    {
        $this->seed(ShieldSeeder::class);

        $this->assertDatabaseHas(Role::class, ['name' => 'super_admin', 'guard_name' => 'web']);
        $this->assertDatabaseHas(Role::class, ['name' => 'admin', 'guard_name' => 'web']);
        $this->assertDatabaseHas(Role::class, ['name' => 'technical_support', 'guard_name' => 'web']);
        $this->assertDatabaseHas(Role::class, ['name' => 'client', 'guard_name' => 'web']);
        $this->assertDatabaseHas(Role::class, ['name' => 'panel_user', 'guard_name' => 'web']);
    }

    public function test_shield_seeder_assigns_ticket_permissions_by_role(): void
    {
        $this->seed(ShieldSeeder::class);

        $admin = Role::findByName('admin');
        $technicalSupport = Role::findByName('technical_support');
        $client = Role::findByName('client');

        $this->assertTrue($admin->hasPermissionTo('delete_ticket'));
        $this->assertTrue($admin->hasPermissionTo('update_user'));
        $this->assertFalse($admin->hasPermissionTo('update_role'));

        $this->assertTrue($technicalSupport->hasPermissionTo('view_any_ticket'));
        $this->assertTrue($technicalSupport->hasPermissionTo('update_ticket'));
        $this->assertFalse($technicalSupport->hasPermissionTo('delete_ticket'));

        $this->assertTrue($client->hasPermissionTo('view_any_ticket'));
        $this->assertTrue($client->hasPermissionTo('create_ticket'));
        $this->assertFalse($client->hasPermissionTo('delete_ticket'));
    }

    public function test_shield_seeder_assigns_inventory_permissions_to_operational_roles(): void
    {
        $this->seed(ShieldSeeder::class);

        $superAdmin = Role::findByName('super_admin');
        $admin = Role::findByName('admin');
        $technicalSupport = Role::findByName('technical_support');

        $this->assertTrue($superAdmin->hasPermissionTo('view_any_inventory::item'));
        $this->assertTrue($superAdmin->hasPermissionTo('view_any_inventory::category'));
        $this->assertTrue($superAdmin->hasPermissionTo('view_any_inventory::transaction'));

        $this->assertTrue($admin->hasPermissionTo('view_any_inventory::item'));
        $this->assertTrue($admin->hasPermissionTo('adjust_stock_inventory_item'));

        $this->assertTrue($technicalSupport->hasPermissionTo('view_any_inventory::item'));
        $this->assertTrue($technicalSupport->hasPermissionTo('assign_inventory_item'));
        $this->assertFalse($technicalSupport->hasPermissionTo('delete_inventory::item'));
    }

    public function test_admin_can_view_inventory_navigation_with_shield_resource_permissions(): void
    {
        $this->seed(ShieldSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user);

        $this->assertTrue(InventoryItemResource::canViewAny());
        $this->assertTrue(InventoryCategoryResource::canViewAny());
        $this->assertTrue(InventoryTransactionResource::canViewAny());
    }

    public function test_super_admin_can_view_inventory_navigation_without_explicit_permissions(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->actingAs($user);

        $this->assertTrue(InventoryItemResource::canViewAny());
        $this->assertTrue(InventoryCategoryResource::canViewAny());
        $this->assertTrue(InventoryTransactionResource::canViewAny());
    }

    public function test_panel_access_is_limited_to_active_shield_roles(): void
    {
        $this->seed(ShieldSeeder::class);

        $client = User::factory()->create();
        $client->assignRole('client');

        $inactiveClient = User::factory()->create(['status' => 0]);
        $inactiveClient->assignRole('client');

        $unassignedUser = User::factory()->create();

        $panel = Panel::make();

        $this->assertTrue($client->refresh()->canAccessPanel($panel));
        $this->assertFalse($inactiveClient->refresh()->canAccessPanel($panel));
        $this->assertFalse($unassignedUser->refresh()->canAccessPanel($panel));
    }

    public function test_legacy_user_roles_are_backfilled_into_spatie_roles(): void
    {
        $this->seed(ShieldSeeder::class);

        $legacyAdminRole = LegacyRole::create(['name' => 'admin']);
        $legacyClientRole = LegacyRole::create(['name' => 'client']);

        $admin = User::factory()->create(['role_id' => $legacyAdminRole->id]);
        $client = User::factory()->create(['role_id' => $legacyClientRole->id]);

        DB::table('model_has_roles')->whereIn('model_id', [$admin->id, $client->id])->delete();

        $migration = require database_path('migrations/2026_05_28_030319_backfill_spatie_roles_from_legacy_user_roles.php');
        $migration->up();

        $this->assertTrue($admin->refresh()->hasRole('admin'));
        $this->assertTrue($client->refresh()->hasRole('client'));
    }

    public function test_user_can_have_multiple_operational_spatie_roles(): void
    {
        $this->seed(ShieldSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(['admin', 'technical_support', 'client']);

        $user->refresh();

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('technical_support'));
        $this->assertTrue($user->hasRole('client'));
    }
}
