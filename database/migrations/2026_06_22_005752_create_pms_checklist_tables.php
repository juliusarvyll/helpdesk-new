<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pms_checklist_templates')) {
            Schema::create('pms_checklist_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pms_checklist_items')) {
            Schema::create('pms_checklist_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('template_id')->constrained('pms_checklist_templates')->cascadeOnDelete();
                $table->string('label');
                $table->string('input_type');
                $table->json('options')->nullable();
                $table->boolean('is_required')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['template_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('preventive_maintenance_sessions')) {
            Schema::create('preventive_maintenance_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('department_id')->constrained('department')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained()->cascadeOnDelete();
                $table->foreignId('started_by')->constrained('users')->cascadeOnDelete();
                $table->dateTime('started_at');
                $table->dateTime('completed_at')->nullable();
                $table->string('status')->default('active');
                $table->text('remarks')->nullable();
                $table->timestamps();
                $table->index(['department_id', 'status']);
                $table->index(['location_id', 'status']);
            });
        }

        if (! Schema::hasTable('preventive_maintenance_asset_checks')) {
            Schema::create('preventive_maintenance_asset_checks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('session_id')->constrained('preventive_maintenance_sessions')->cascadeOnDelete();
                $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('inventory_item_serial_number_id');
                $table->foreign('inventory_item_serial_number_id', 'pms_asset_checks_serial_fk')
                    ->references('id')->on('inventory_item_serial_numbers')->cascadeOnDelete();
                $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('checklist_template_id');
                $table->foreign('checklist_template_id', 'pms_asset_checks_template_fk')
                    ->references('id')->on('pms_checklist_templates')->restrictOnDelete();
                $table->string('status')->default('pending');
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
                $table->unique(['session_id', 'inventory_item_serial_number_id'], 'pms_asset_checks_session_serial_unique');
                $table->index(['inventory_item_serial_number_id', 'completed_at'], 'pms_asset_checks_serial_completed_index');
                $table->index(['status', 'completed_at']);
            });
        }

        if (! Schema::hasTable('preventive_maintenance_check_results')) {
            Schema::create('preventive_maintenance_check_results', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('asset_check_id');
                $table->foreign('asset_check_id', 'pms_check_results_asset_check_fk')
                    ->references('id')->on('preventive_maintenance_asset_checks')->cascadeOnDelete();
                $table->foreignId('checklist_item_id');
                $table->foreign('checklist_item_id', 'pms_check_results_item_fk')
                    ->references('id')->on('pms_checklist_items')->cascadeOnDelete();
                $table->text('value');
                $table->text('remarks')->nullable();
                $table->timestamps();
                $table->unique(['asset_check_id', 'checklist_item_id'], 'pms_check_results_check_item_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('preventive_maintenance_check_results');
        Schema::dropIfExists('preventive_maintenance_asset_checks');
        Schema::dropIfExists('preventive_maintenance_sessions');
        Schema::dropIfExists('pms_checklist_items');
        Schema::dropIfExists('pms_checklist_templates');
    }
};
