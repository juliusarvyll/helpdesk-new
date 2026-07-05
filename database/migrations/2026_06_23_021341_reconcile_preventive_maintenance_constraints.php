<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! $this->hasForeignKeyOn('preventive_maintenance_schedules', 'inventory_item_serial_number_id')) {
            Schema::table('preventive_maintenance_schedules', function (Blueprint $table): void {
                $table->foreign('inventory_item_serial_number_id', 'pms_reconcile_schedule_serial_fk')
                    ->references('id')->on('inventory_item_serial_numbers')->cascadeOnDelete();
            });
        }

        if (! $this->hasForeignKeyOn('preventive_maintenance_schedules', 'assigned_to_user_id')) {
            Schema::table('preventive_maintenance_schedules', function (Blueprint $table): void {
                $table->foreign('assigned_to_user_id', 'pms_reconcile_schedule_assigned_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! $this->hasForeignKeyOn('preventive_maintenance_schedules', 'created_by')) {
            Schema::table('preventive_maintenance_schedules', function (Blueprint $table): void {
                $table->foreign('created_by', 'pms_reconcile_schedule_creator_fk')
                    ->references('id')->on('users')->cascadeOnDelete();
            });
        }

        $this->addIndexIfMissing('preventive_maintenance_schedules', ['is_active', 'next_due_at'], 'pms_reconcile_schedule_active_due_idx');
        $this->addIndexIfMissing('preventive_maintenance_schedules', ['department_id', 'next_due_at'], 'pms_reconcile_schedule_department_due_idx');

        if (! $this->hasForeignKeyOn('preventive_maintenance_asset_checks', 'inventory_item_serial_number_id')) {
            Schema::table('preventive_maintenance_asset_checks', function (Blueprint $table): void {
                $table->foreign('inventory_item_serial_number_id', 'pms_reconcile_asset_serial_fk')
                    ->references('id')->on('inventory_item_serial_numbers')->cascadeOnDelete();
            });
        }

        if (! $this->hasForeignKeyOn('preventive_maintenance_asset_checks', 'checked_by')) {
            Schema::table('preventive_maintenance_asset_checks', function (Blueprint $table): void {
                $table->foreign('checked_by', 'pms_reconcile_asset_checked_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! $this->hasForeignKeyOn('preventive_maintenance_asset_checks', 'checklist_template_id')) {
            Schema::table('preventive_maintenance_asset_checks', function (Blueprint $table): void {
                $table->foreign('checklist_template_id', 'pms_reconcile_asset_template_fk')
                    ->references('id')->on('pms_checklist_templates')->restrictOnDelete();
            });
        }

        if (! $this->hasForeignKeyOn('preventive_maintenance_asset_checks', 'ticket_id')) {
            Schema::table('preventive_maintenance_asset_checks', function (Blueprint $table): void {
                $table->foreign('ticket_id', 'pms_reconcile_asset_ticket_fk')
                    ->references('id')->on('tickets')->nullOnDelete();
            });
        }

        $this->addIndexIfMissing('preventive_maintenance_asset_checks', ['session_id', 'inventory_item_serial_number_id'], 'pms_reconcile_session_serial_unique', true);
        $this->addIndexIfMissing('preventive_maintenance_asset_checks', ['inventory_item_serial_number_id', 'completed_at'], 'pms_reconcile_serial_completed_idx');
        $this->addIndexIfMissing('preventive_maintenance_asset_checks', ['status', 'completed_at'], 'pms_reconcile_status_completed_idx');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropForeignIfPresent('preventive_maintenance_asset_checks', 'pms_reconcile_asset_serial_fk');
        $this->dropForeignIfPresent('preventive_maintenance_asset_checks', 'pms_reconcile_asset_checked_by_fk');
        $this->dropForeignIfPresent('preventive_maintenance_asset_checks', 'pms_reconcile_asset_template_fk');
        $this->dropForeignIfPresent('preventive_maintenance_asset_checks', 'pms_reconcile_asset_ticket_fk');
        $this->dropIndexIfPresent('preventive_maintenance_asset_checks', 'pms_reconcile_session_serial_unique', true);
        $this->dropIndexIfPresent('preventive_maintenance_asset_checks', 'pms_reconcile_serial_completed_idx');
        $this->dropIndexIfPresent('preventive_maintenance_asset_checks', 'pms_reconcile_status_completed_idx');

        $this->dropForeignIfPresent('preventive_maintenance_schedules', 'pms_reconcile_schedule_serial_fk');
        $this->dropForeignIfPresent('preventive_maintenance_schedules', 'pms_reconcile_schedule_assigned_fk');
        $this->dropForeignIfPresent('preventive_maintenance_schedules', 'pms_reconcile_schedule_creator_fk');
        $this->dropIndexIfPresent('preventive_maintenance_schedules', 'pms_reconcile_schedule_active_due_idx');
        $this->dropIndexIfPresent('preventive_maintenance_schedules', 'pms_reconcile_schedule_department_due_idx');
    }

    private function hasForeignKeyOn(string $table, string $column): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === [$column]);
    }

    private function hasIndexOn(string $table, array $columns, bool $unique = false): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $index): bool => $index['columns'] === $columns && (! $unique || $index['unique']),
        );
    }

    private function hasForeignKeyNamed(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => $foreignKey['name'] === $name);
    }

    private function hasIndexNamed(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => $index['name'] === $name);
    }

    private function addIndexIfMissing(string $tableName, array $columns, string $name, bool $unique = false): void
    {
        if ($this->hasIndexOn($tableName, $columns, $unique)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $name, $unique): void {
            $unique ? $table->unique($columns, $name) : $table->index($columns, $name);
        });
    }

    private function dropForeignIfPresent(string $tableName, string $name): void
    {
        if (! $this->hasForeignKeyNamed($tableName, $name)) {
            return;
        }

        Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign($name));
    }

    private function dropIndexIfPresent(string $tableName, string $name, bool $unique = false): void
    {
        if (! $this->hasIndexNamed($tableName, $name)) {
            return;
        }

        Schema::table($tableName, fn (Blueprint $table) => $unique ? $table->dropUnique($name) : $table->dropIndex($name));
    }
};
