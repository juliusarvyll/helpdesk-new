<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_categories', function (Blueprint $table): void {
            $table->boolean('is_it_asset')->default(false)->after('type')->index();
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->boolean('is_it_asset')->default(false)->after('inventory_category_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropColumn('is_it_asset');
        });

        Schema::table('inventory_categories', function (Blueprint $table): void {
            $table->dropColumn('is_it_asset');
        });
    }
};
