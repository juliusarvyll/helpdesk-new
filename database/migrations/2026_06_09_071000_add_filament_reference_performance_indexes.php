<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issue_category', function (Blueprint $table): void {
            if (! Schema::hasIndex('issue_category', 'issue_category_name_index')) {
                $table->index('name', 'issue_category_name_index');
            }
        });

        Schema::table('locations', function (Blueprint $table): void {
            if (! Schema::hasIndex('locations', 'locations_department_deleted_name_index')) {
                $table->index(['department_id', 'is_deleted', 'name'], 'locations_department_deleted_name_index');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasIndex('users', 'users_deleted_status_name_index')) {
                $table->index(['is_deleted', 'status', 'name'], 'users_deleted_status_name_index');
            }
        });

        Schema::table('roles', function (Blueprint $table): void {
            if (! Schema::hasIndex('roles', 'roles_name_index')) {
                $table->index('name', 'roles_name_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            if (Schema::hasIndex('roles', 'roles_name_index')) {
                $table->dropIndex('roles_name_index');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasIndex('users', 'users_deleted_status_name_index')) {
                $table->dropIndex('users_deleted_status_name_index');
            }
        });

        Schema::table('locations', function (Blueprint $table): void {
            if (Schema::hasIndex('locations', 'locations_department_deleted_name_index')) {
                $table->dropIndex('locations_department_deleted_name_index');
            }
        });

        Schema::table('issue_category', function (Blueprint $table): void {
            if (Schema::hasIndex('issue_category', 'issue_category_name_index')) {
                $table->dropIndex('issue_category_name_index');
            }
        });
    }
};
