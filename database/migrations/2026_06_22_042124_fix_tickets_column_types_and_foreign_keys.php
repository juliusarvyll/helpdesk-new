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

            // Add foreign key constraints
            $table->foreign('issue_id')->references('id')->on('issue_list')->nullOnDelete();
            $table->foreign('client_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('department')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->nullOnDelete();
            $table->foreign('inventory_item_serial_number_id', 'tickets_serial_number_id_foreign')
                ->references('id')->on('inventory_item_serial_numbers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropForeign(['issue_id']);
            $table->dropForeign(['client_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['inventory_item_id']);
            $table->dropForeign('tickets_serial_number_id_foreign');

            $table->string('issue_id', 191)->nullable()->change();
            $table->integer('client_id')->nullable()->change();
            $table->string('client_confirmation', 191)->default('0')->change();
            $table->dropColumn('assigned_at');
        });
    }
};
