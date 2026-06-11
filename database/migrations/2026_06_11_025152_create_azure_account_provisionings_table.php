<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('azure_account_provisionings', function (Blueprint $table) {
            $table->id();
            $table->string('account_type');
            $table->string('display_name');
            $table->string('given_name');
            $table->string('surname');
            $table->string('user_principal_name')->unique();
            $table->string('mail_nickname');
            $table->string('usage_location', 2)->default('US');
            $table->uuid('license_sku_id')->nullable();
            $table->string('license_sku_part_number')->nullable();
            $table->string('azure_user_id')->nullable()->index();
            $table->text('temporary_password')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('last_error')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('azure_account_provisionings');
    }
};
