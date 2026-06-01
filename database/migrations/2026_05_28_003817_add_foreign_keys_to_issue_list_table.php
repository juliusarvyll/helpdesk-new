<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issue_list', function (Blueprint $table) {
            $table->unsignedBigInteger('issue_category_id')->change();
            $table->foreign('issue_category_id')->references('id')->on('issue_category')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('issue_list', function (Blueprint $table) {
            $table->dropForeign(['issue_category_id']);
        });
    }
};
