<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE azure_account_provisionings MODIFY usage_location VARCHAR(2) NOT NULL DEFAULT 'PH'");
        DB::table('azure_account_provisionings')
            ->where('usage_location', 'US')
            ->update(['usage_location' => 'PH']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE azure_account_provisionings MODIFY usage_location VARCHAR(2) NOT NULL DEFAULT 'US'");
    }
};
