<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tickets')
            ->whereIn('status', ['pending/closed', 'overdue/closed'])
            ->update(['status' => 'closed']);
    }

    public function down(): void
    {
        //
    }
};
