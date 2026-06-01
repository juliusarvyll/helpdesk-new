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
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $rolePriority = ['super_admin', 'technical_support', 'admin', 'client'];
        $roleIds = DB::table('roles')
            ->whereIn('name', $rolePriority)
            ->pluck('id', 'name');

        if ($roleIds->isEmpty()) {
            return;
        }

        $operationalRoleIds = $roleIds->values()->all();

        DB::table('model_has_roles')
            ->select('model_id')
            ->where('model_type', 'App\\Models\\User')
            ->whereIn('role_id', $operationalRoleIds)
            ->groupBy('model_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('model_id')
            ->chunk(500, function ($users) use ($roleIds, $rolePriority, $operationalRoleIds): void {
                foreach ($users as $user) {
                    $assignedRoleIds = DB::table('model_has_roles')
                        ->where('model_type', 'App\\Models\\User')
                        ->where('model_id', $user->model_id)
                        ->whereIn('role_id', $operationalRoleIds)
                        ->pluck('role_id')
                        ->all();

                    $roleToKeep = collect($rolePriority)
                        ->map(fn (string $roleName) => $roleIds[$roleName] ?? null)
                        ->first(fn ($roleId): bool => $roleId !== null && in_array($roleId, $assignedRoleIds));

                    if (! $roleToKeep) {
                        continue;
                    }

                    DB::table('model_has_roles')
                        ->where('model_type', 'App\\Models\\User')
                        ->where('model_id', $user->model_id)
                        ->whereIn('role_id', array_values(array_diff($operationalRoleIds, [$roleToKeep])))
                        ->delete();
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
