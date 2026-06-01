<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class InventoryPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view_any_inventory::item',
            'view_inventory::item',
            'create_inventory::item',
            'update_inventory::item',
            'delete_inventory::item',
            'assign_inventory_item',
            'adjust_stock_inventory_item',
            'retire_inventory_item',
            'view_any_inventory::transaction',
            'view_inventory::transaction',
            'view_any_inventory::category',
            'view_inventory::category',
            'create_inventory::category',
            'update_inventory::category',
            'delete_inventory::category',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])->givePermissionTo($permissions);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->givePermissionTo($permissions);
    }
}
