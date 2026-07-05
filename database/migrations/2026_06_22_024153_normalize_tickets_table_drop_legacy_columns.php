<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $columns = [
                'category',
                'issue',
                'client',
                'department',
                'position',
                'role',
                'location',
                'technical_support',
                'technical_support_id',
                'created_ticket',
                'asset_id',
                'asset_name',
            ];

            $existing = array_filter($columns, fn (string $col): bool => Schema::hasColumn('tickets', $col));

            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('category', 191)->nullable();
            $table->string('issue', 191)->nullable();
            $table->string('client', 191)->nullable();
            $table->string('department', 191)->nullable();
            $table->string('position', 191)->nullable();
            $table->string('role', 191)->nullable();
            $table->string('location', 500)->nullable();
            $table->text('technical_support')->nullable();
            $table->string('technical_support_id', 191)->nullable();
            $table->string('created_ticket', 191)->nullable();
            $table->string('asset_id', 191)->nullable();
            $table->string('asset_name', 191)->nullable();
        });
    }
};
