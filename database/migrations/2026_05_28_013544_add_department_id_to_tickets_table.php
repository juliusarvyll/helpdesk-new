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
            $table->unsignedBigInteger('department_id')->nullable()->after('client_id');
            $table->foreign('department_id')->references('id')->on('department')->onDelete('set null');
        });

        DB::statement('
            UPDATE tickets t
            INNER JOIN users u ON t.client_id = u.id
            SET t.department_id = u.department_id
            WHERE u.department_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
