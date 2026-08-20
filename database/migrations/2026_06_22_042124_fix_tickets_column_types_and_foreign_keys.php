<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullify invalid client_id = 0 (no matching user)
        DB::table('tickets')->where('client_id', 0)->update(['client_id' => null]);

        Schema::table('tickets', function (Blueprint $table): void {
            // Fix issue_id: varchar -> bigint unsigned
            $table->unsignedBigInteger('issue_id')->nullable()->change();

            // Fix client_id: int -> bigint unsigned
            $table->unsignedBigInteger('client_id')->nullable()->change();

            // Fix client_confirmation: varchar -> boolean
            $table->boolean('client_confirmation')->default(false)->change();

            // Add missing assigned_at column
            if (! Schema::hasColumn('tickets', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('support_assignment_status');
            }

            // Note: foreign key constraints for issue_id, client_id, department_id,
            // created_by, inventory_item_id, and inventory_item_serial_number_id are
            // already added by earlier migrations (2026_05_28_003713, 2026_05_28_013544,
            // 2026_05_28_035606, 2026_05_28_081500) and must not be re-declared here.
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('issue_id', 191)->nullable()->change();
            $table->integer('client_id')->nullable()->change();
            $table->string('client_confirmation', 191)->default('0')->change();
            $table->dropColumn('assigned_at');
        });
    }
};
