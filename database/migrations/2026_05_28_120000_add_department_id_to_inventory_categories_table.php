<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_categories', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('id')
                ->constrained('department')
                ->nullOnDelete();

            $table->index(['department_id', 'type', 'is_deleted']);
        });

        $fallbackDepartmentId = DB::table('department')->where('is_deleted', 0)->orderBy('id')->value('id')
            ?? DB::table('department')->orderBy('id')->value('id');

        if ($fallbackDepartmentId) {
            DB::table('inventory_categories')->update(['department_id' => $fallbackDepartmentId]);
        }
    }

    public function down(): void
    {
        Schema::table('inventory_categories', function (Blueprint $table) {
            $table->dropIndex(['department_id', 'type', 'is_deleted']);
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
