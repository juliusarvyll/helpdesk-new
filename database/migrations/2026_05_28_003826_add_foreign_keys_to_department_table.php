<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_head')->nullable()->change();
            $table->foreign('unit_head')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('department', function (Blueprint $table) {
            $table->dropForeign(['unit_head']);
        });
    }
};
