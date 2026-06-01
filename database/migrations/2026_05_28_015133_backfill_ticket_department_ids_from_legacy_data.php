<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'department_id')) {
            return;
        }

        if (Schema::hasColumn('tickets', 'department')) {
            DB::statement('
                UPDATE tickets
                INNER JOIN department ON department.name = tickets.department
                SET tickets.department_id = department.id
                WHERE tickets.department_id IS NULL
                    AND tickets.department IS NOT NULL
                    AND tickets.department != ""
            ');
        }

        if (Schema::hasColumn('tickets', 'client_id') && Schema::hasColumn('users', 'department_id')) {
            DB::statement('
                UPDATE tickets
                INNER JOIN users ON users.id = tickets.client_id
                SET tickets.department_id = users.department_id
                WHERE tickets.department_id IS NULL
                    AND users.department_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        // Data backfill only; leave existing ticket ownership intact on rollback.
    }
};
