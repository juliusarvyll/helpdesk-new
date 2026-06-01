<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('role') || ! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $roleAliases = [
            'admin' => 'admin',
            'administrator' => 'admin',
            'super_admin' => 'super_admin',
            'super admin' => 'super_admin',
            'technical' => 'technical_support',
            'technical support' => 'technical_support',
            'technical_support' => 'technical_support',
            'client' => 'client',
            'user' => 'client',
        ];

        foreach (array_unique(array_values($roleAliases)) as $roleName) {
            DB::table('roles')->insertOrIgnore([
                'name' => $roleName,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $spatieRoleIds = DB::table('roles')
            ->whereIn('name', array_unique(array_values($roleAliases)))
            ->pluck('id', 'name');

        DB::table('users')
            ->join('role', 'role.id', '=', 'users.role_id')
            ->select('users.id as user_id', 'role.name as legacy_role_name')
            ->orderBy('users.id')
            ->chunkById(500, function ($users) use ($roleAliases, $spatieRoleIds): void {
                $assignments = $users
                    ->map(function ($user) use ($roleAliases, $spatieRoleIds): ?array {
                        $roleName = $roleAliases[strtolower(trim($user->legacy_role_name))] ?? null;

                        if (! $roleName || ! isset($spatieRoleIds[$roleName])) {
                            return null;
                        }

                        return [
                            'role_id' => $spatieRoleIds[$roleName],
                            'model_type' => 'App\\Models\\User',
                            'model_id' => $user->user_id,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                if ($assignments !== []) {
                    DB::table('model_has_roles')->insertOrIgnore($assignments);
                }
            }, 'users.id', 'user_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
