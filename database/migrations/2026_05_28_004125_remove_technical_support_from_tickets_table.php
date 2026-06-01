<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['technical_support', 'department', 'position', 'role', 'category', 'client']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->text('technical_support')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('role')->nullable();
            $table->string('category')->nullable();
            $table->string('client')->nullable();
        });
    }
};
