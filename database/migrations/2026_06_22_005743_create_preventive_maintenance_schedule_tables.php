<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('preventive_maintenance_schedules')) {
            Schema::create('preventive_maintenance_schedules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('department_id')->constrained('department')->cascadeOnDelete();
                $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('inventory_item_serial_number_id')->nullable();
                $table->foreign('inventory_item_serial_number_id', 'pms_schedules_serial_id_foreign')
                    ->references('id')->on('inventory_item_serial_numbers')->cascadeOnDelete();
                $table->string('title');
                $table->text('description');
                $table->string('frequency');
                $table->unsignedInteger('interval_value')->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('next_due_at');
                $table->dateTime('last_generated_at')->nullable();
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->index(['is_active', 'next_due_at']);
                $table->index(['department_id', 'next_due_at']);
            });
        }

        if (! Schema::hasTable('preventive_maintenance_logs')) {
            Schema::create('preventive_maintenance_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('schedule_id')->constrained('preventive_maintenance_schedules')->cascadeOnDelete();
                $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('job_order_id')->nullable()->constrained()->nullOnDelete();
                $table->dateTime('generated_at');
                $table->dateTime('completed_at')->nullable();
                $table->string('status')->default('generated');
                $table->text('remarks')->nullable();
                $table->timestamps();
                $table->index(['schedule_id', 'status']);
                $table->index(['generated_at', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('preventive_maintenance_logs');
        Schema::dropIfExists('preventive_maintenance_schedules');
    }
};
