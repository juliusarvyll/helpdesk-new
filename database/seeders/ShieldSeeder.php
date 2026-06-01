<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const RESOURCE_PERMISSIONS = [
        'view',
        'view_any',
        'create',
        'update',
        'restore',
        'restore_any',
        'replicate',
        'reorder',
        'delete',
        'delete_any',
        'force_delete',
        'force_delete_any',
    ];

    /**
     * @var array<int, string>
     */
    private const RESOURCE_KEYS = [
        'role',
        'department',
        'inventory::category',
        'inventory::item',
        'inventory::transaction',
        'issue::category',
        'issue::list',
        'position',
        'ticket',
        'user',
    ];

    /**
     * @var array<int, string>
     */
    private const CUSTOM_PERMISSIONS = [
        'assign_inventory_item',
        'adjust_stock_inventory_item',
        'retire_inventory_item',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        static::makeRolesWithPermissions(static::rolesWithPermissions());

        $this->command->info('Shield Seeding Completed.');
    }

    /**
     * @return array<int, array{name: string, guard_name: string, permissions: array<int, string>}>
     */
    public static function rolesWithPermissions(): array
    {
        return [
            [
                'name' => 'super_admin',
                'guard_name' => 'web',
                'permissions' => static::allPermissions(),
            ],
            [
                'name' => 'admin',
                'guard_name' => 'web',
                'permissions' => static::adminPermissions(),
            ],
            [
                'name' => 'technical_support',
                'guard_name' => 'web',
                'permissions' => static::technicalSupportPermissions(),
            ],
            [
                'name' => 'client',
                'guard_name' => 'web',
                'permissions' => static::clientPermissions(),
            ],
            [
                'name' => 'panel_user',
                'guard_name' => 'web',
                'permissions' => [],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allPermissions(): array
    {
        return collect(self::RESOURCE_KEYS)
            ->flatMap(fn (string $resource): array => static::permissionsFor($resource))
            ->merge(self::CUSTOM_PERMISSIONS)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function adminPermissions(): array
    {
        return collect(['department', 'inventory::category', 'inventory::item', 'inventory::transaction', 'issue::category', 'issue::list', 'position', 'ticket', 'user'])
            ->flatMap(fn (string $resource): array => static::permissionsFor($resource))
            ->merge(self::CUSTOM_PERMISSIONS)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function technicalSupportPermissions(): array
    {
        return [
            'view_ticket',
            'view_any_ticket',
            'update_ticket',
            'view_inventory::item',
            'view_any_inventory::item',
            'assign_inventory_item',
            'adjust_stock_inventory_item',
            'retire_inventory_item',
            'view_any_inventory::transaction',
            'view_issue::category',
            'view_any_issue::category',
            'view_issue::list',
            'view_any_issue::list',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function clientPermissions(): array
    {
        return [
            'view_ticket',
            'view_any_ticket',
            'create_ticket',
            'update_ticket',
            'view_issue::category',
            'view_any_issue::category',
            'view_issue::list',
            'view_any_issue::list',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function permissionsFor(string $resource): array
    {
        return collect(self::RESOURCE_PERMISSIONS)
            ->map(fn (string $permission): string => "{$permission}_{$resource}")
            ->all();
    }

    /**
     * @param  array<int, array{name: string, guard_name: string, permissions: array<int, string>}>  $rolesWithPermissions
     */
    protected static function makeRolesWithPermissions(array $rolesWithPermissions): void
    {
        /** @var class-string<\Spatie\Permission\Models\Role> $roleModel */
        $roleModel = Utils::getRoleModel();
        /** @var class-string<\Spatie\Permission\Models\Permission> $permissionModel */
        $permissionModel = Utils::getPermissionModel();

        foreach ($rolesWithPermissions as $rolePlusPermission) {
            $role = $roleModel::firstOrCreate([
                'name' => $rolePlusPermission['name'],
                'guard_name' => $rolePlusPermission['guard_name'],
            ]);

            $permissionModels = collect($rolePlusPermission['permissions'])
                ->map(fn (string $permission) => $permissionModel::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => $rolePlusPermission['guard_name'],
                ]))
                ->all();

            $role->syncPermissions($permissionModels);
        }
    }
}
