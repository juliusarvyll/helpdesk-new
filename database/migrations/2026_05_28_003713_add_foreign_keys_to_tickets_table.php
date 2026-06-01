<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('issue_id')->nullable()->change();
            $table->unsignedBigInteger('client_id')->nullable()->change();
            $table->unsignedBigInteger('created_by')->nullable()->after('client_confirmation');

            $table->foreign('issue_id')->references('id')->on('issue_list')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['issue_id']);
            $table->dropForeign(['client_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
