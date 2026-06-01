<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('inventory_item_serial_number_id')
                ->nullable()
                ->after('inventory_item_id')
                ->constrained('inventory_item_serial_numbers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['inventory_item_serial_number_id']);
            $table->dropColumn('inventory_item_serial_number_id');
        });
    }
};
