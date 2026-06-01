<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_item_serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('serial_number')->unique();
            $table->enum('status', ['available', 'assigned', 'in_repair', 'retired', 'lost', 'disposed'])->default('available');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['inventory_item_id', 'status']);
        });

        // Remove serial_number from inventory_items since it's now in child table
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('serial_number')->nullable()->after('description');
        });

        Schema::dropIfExists('inventory_item_serial_numbers');
    }
};
