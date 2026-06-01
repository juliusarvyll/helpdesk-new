<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            INSERT INTO ticket_technical_support (ticket_id, user_id, created_at, updated_at)
            SELECT id, CAST(technical_support_id AS UNSIGNED), NOW(), NOW()
            FROM tickets
            WHERE technical_support_id IS NOT NULL 
            AND technical_support_id != ""
            AND technical_support_id REGEXP "^[0-9]+$"
            AND NOT EXISTS (
                SELECT 1 FROM ticket_technical_support 
                WHERE ticket_id = tickets.id 
                AND user_id = CAST(tickets.technical_support_id AS UNSIGNED)
            )
        ');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('technical_support_id');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('technical_support_id')->nullable();
        });
    }
};
