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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->constrained()->cascadeOnDelete();
            $table->string('asset_tag')->unique()->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('serial_number')->nullable();
            $table->enum('status', ['available', 'assigned', 'in_repair', 'retired', 'lost', 'disposed'])->default('available');
            $table->integer('quantity')->default(1);
            $table->string('unit')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('department')->nullOnDelete();
            $table->foreignId('current_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->date('purchased_at')->nullable();
            $table->date('warranty_expires_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->index(['status', 'is_deleted']);
            $table->index(['inventory_category_id', 'status']);
            $table->index('assigned_to_user_id');
            $table->index('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
