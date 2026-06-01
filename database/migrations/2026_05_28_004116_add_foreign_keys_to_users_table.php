<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('photo');
            $table->unsignedBigInteger('position_id')->nullable()->after('department_id');
            $table->unsignedBigInteger('role_id')->nullable()->after('position_id');
        });

        DB::table('users')->whereNotNull('department')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $deptId = DB::table('department')->where('name', $user->department)->value('id');
                if ($deptId) {
                    DB::table('users')->where('id', $user->id)->update(['department_id' => $deptId]);
                }
            }
        });

        DB::table('users')->whereNotNull('position')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $posId = DB::table('position')->where('name', $user->position)->value('id');
                if ($posId) {
                    DB::table('users')->where('id', $user->id)->update(['position_id' => $posId]);
                }
            }
        });

        DB::table('users')->whereNotNull('role')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $roleId = DB::table('role')->where('name', $user->role)->value('id');
                if ($roleId) {
                    DB::table('users')->where('id', $user->id)->update(['role_id' => $roleId]);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['department', 'position', 'role']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('department')->onDelete('set null');
            $table->foreign('position_id')->references('id')->on('position')->onDelete('set null');
            $table->foreign('role_id')->references('id')->on('role')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['position_id']);
            $table->dropForeign(['role_id']);
            $table->dropColumn(['department_id', 'position_id', 'role_id']);
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('role')->nullable()->default('user');
        });
    }
};
