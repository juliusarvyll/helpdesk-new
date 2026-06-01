<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('support_assignment_status');
            }
        });

        $this->addIndexIfMissing('tickets', ['status'], 'tickets_status_index');
        $this->addIndexIfMissing('tickets', ['priority'], 'tickets_priority_index');
        $this->addIndexIfMissing('tickets', ['created_at'], 'tickets_created_at_index');
        $this->addIndexIfMissing('tickets', ['assigned_at'], 'tickets_assigned_at_index');
        $this->addIndexIfMissing('tickets', ['department_id', 'status'], 'tickets_department_id_status_index');
        $this->addIndexIfMissing('tickets', ['client_id', 'created_at'], 'tickets_client_id_created_at_index');
        $this->addIndexIfMissing('tickets', ['created_by', 'created_at'], 'tickets_created_by_created_at_index');
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $this->dropIndexIfExists('tickets', 'tickets_created_by_created_at_index', $table);
            $this->dropIndexIfExists('tickets', 'tickets_client_id_created_at_index', $table);
            $this->dropIndexIfExists('tickets', 'tickets_department_id_status_index', $table);
            $this->dropIndexIfExists('tickets', 'tickets_assigned_at_index', $table);
            $this->dropIndexIfExists('tickets', 'tickets_created_at_index', $table);
            $this->dropIndexIfExists('tickets', 'tickets_priority_index', $table);
            $this->dropIndexIfExists('tickets', 'tickets_status_index', $table);

            if (Schema::hasColumn('tickets', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }
        });
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $indexName));
    }

    private function dropIndexIfExists(string $table, string $indexName, Blueprint $blueprint): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        $blueprint->dropIndex($indexName);
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
