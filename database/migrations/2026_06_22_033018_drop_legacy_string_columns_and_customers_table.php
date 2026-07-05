<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop legacy denormalized string columns from users (replaced by FK columns)
        Schema::table('users', function (Blueprint $table): void {
            $toDrop = array_filter(
                ['department', 'position', 'role'],
                fn (string $col): bool => Schema::hasColumn('users', $col)
            );

            if ($toDrop) {
                $table->dropColumn(array_values($toDrop));
            }
        });

        // Drop orphaned legacy table with no model or code references
        Schema::dropIfExists('Customers');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('department', 191)->nullable()->after('photo');
            $table->string('position', 191)->nullable()->after('department');
            $table->string('role', 191)->nullable()->default('user')->after('position');
        });
    }
};
