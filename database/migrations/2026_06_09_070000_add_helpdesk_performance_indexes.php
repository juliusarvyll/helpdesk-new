<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasIndex('tickets', 'tickets_department_status_end_time_index')) {
                $table->index(['department_id', 'status', 'end_time'], 'tickets_department_status_end_time_index');
            }

            if (! Schema::hasIndex('tickets', 'tickets_department_status_created_at_index')) {
                $table->index(['department_id', 'status', 'created_at'], 'tickets_department_status_created_at_index');
            }

            if (! Schema::hasIndex('tickets', 'tickets_department_created_at_index')) {
                $table->index(['department_id', 'created_at'], 'tickets_department_created_at_index');
            }
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            if (! Schema::hasIndex('inventory_items', 'inventory_items_department_deleted_created_at_index')) {
                $table->index(['department_id', 'is_deleted', 'created_at'], 'inventory_items_department_deleted_created_at_index');
            }
        });

        Schema::table('system_logs', function (Blueprint $table): void {
            if (! Schema::hasIndex('system_logs', 'system_logs_user_created_at_index')) {
                $table->index(['user_id', 'created_at'], 'system_logs_user_created_at_index');
            }

            if (! Schema::hasIndex('system_logs', 'system_logs_created_at_index')) {
                $table->index('created_at', 'system_logs_created_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table): void {
            $table->dropIndex('system_logs_created_at_index');
            $table->dropIndex('system_logs_user_created_at_index');
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropIndex('inventory_items_department_deleted_created_at_index');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex('tickets_department_created_at_index');
            $table->dropIndex('tickets_department_status_created_at_index');
            $table->dropIndex('tickets_department_status_end_time_index');
        });
    }
};
