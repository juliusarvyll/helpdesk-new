<?php

use Database\Seeders\ShieldSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('helpdesk:seed-legacy-compatibility', function (): int {
    $this->info('Creating Laravel runtime tables...');

    if (! Schema::hasTable('cache')) {
        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
    }

    if (! Schema::hasTable('cache_locks')) {
        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    if (! Schema::hasTable('sessions')) {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    if (! Schema::hasTable('jobs')) {
        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    if (! Schema::hasTable('job_batches')) {
        Schema::create('job_batches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
    }

    if (! Schema::hasTable('failed_jobs')) {
        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    if (! Schema::hasTable('notifications')) {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    $this->info('Adding compatibility columns...');

    Schema::table('users', function (Blueprint $table): void {
        if (! Schema::hasColumn('users', 'department_id')) {
            $table->unsignedBigInteger('department_id')->nullable()->after('photo');
        }

        if (! Schema::hasColumn('users', 'position_id')) {
            $table->unsignedBigInteger('position_id')->nullable()->after('department_id');
        }

        if (! Schema::hasColumn('users', 'role_id')) {
            $table->unsignedBigInteger('role_id')->nullable()->after('position_id');
        }
    });

    Schema::table('tickets', function (Blueprint $table): void {
        if (! Schema::hasColumn('tickets', 'asset_id')) {
            $table->string('asset_id')->nullable()->after('client_id');
        }

        if (! Schema::hasColumn('tickets', 'asset_name')) {
            $table->string('asset_name')->nullable()->after('asset_id');
        }

        if (! Schema::hasColumn('tickets', 'inventory_item_id')) {
            $table->unsignedBigInteger('inventory_item_id')->nullable()->after('asset_name');
        }

        if (! Schema::hasColumn('tickets', 'inventory_item_serial_number_id')) {
            $table->unsignedBigInteger('inventory_item_serial_number_id')->nullable()->after('inventory_item_id');
        }

        if (! Schema::hasColumn('tickets', 'department_id')) {
            $table->unsignedBigInteger('department_id')->nullable()->after('inventory_item_serial_number_id');
        }

        if (! Schema::hasColumn('tickets', 'created_by')) {
            $table->unsignedBigInteger('created_by')->nullable()->after('client_confirmation');
        }
    });

    $this->info('Creating compatibility pivot tables...');

    if (! Schema::hasTable('department_user')) {
        Schema::create('department_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('is_deleted')->default(0);
            $table->timestamps();
            $table->primary(['department_id', 'user_id']);
            $table->index('user_id');
        });
    }

    if (! Schema::hasTable('ticket_technical_support')) {
        Schema::create('ticket_technical_support', function (Blueprint $table): void {
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->primary(['ticket_id', 'user_id']);
            $table->index('user_id');
        });
    }

    $this->info('Seeding Shield roles and permissions...');
    $this->call('db:seed', [
        '--class' => ShieldSeeder::class,
        '--force' => true,
    ]);

    $this->info('Backfilling users, tenants, and ticket assignments...');

    if (Schema::hasColumn('users', 'department')) {
        DB::statement('
            UPDATE users u
            LEFT JOIN department d ON d.name = u.department
            SET u.department_id = d.id
            WHERE u.department_id IS NULL
        ');
    }

    if (Schema::hasColumn('users', 'position')) {
        DB::statement('
            UPDATE users u
            LEFT JOIN position p ON p.name = u.position
            SET u.position_id = p.id
            WHERE u.position_id IS NULL
        ');
    }

    if (Schema::hasColumn('users', 'role')) {
        DB::statement('
            UPDATE users u
            LEFT JOIN role r ON r.name = u.role
            SET u.role_id = r.id
            WHERE u.role_id IS NULL
        ');
    }

    DB::statement('
        INSERT IGNORE INTO department_user (department_id, user_id, is_deleted, created_at, updated_at)
        SELECT department_id, id, 0, created_at, updated_at
        FROM users
        WHERE department_id IS NOT NULL
    ');

    if (Schema::hasColumn('users', 'role')) {
        DB::statement('
            INSERT IGNORE INTO model_has_roles (role_id, model_type, model_id)
            SELECT roles.id, "App\\\\Models\\\\User", users.id
            FROM users
            JOIN roles ON roles.name = users.role AND roles.guard_name = "web"
            WHERE users.role IN ("admin", "client", "super_admin", "technical_support", "panel_user")
        ');
    }

    if (Schema::hasColumn('users', 'role_id') && Schema::hasTable('role')) {
        DB::statement('
            INSERT IGNORE INTO model_has_roles (role_id, model_type, model_id)
            SELECT roles.id, "App\\\\Models\\\\User", users.id
            FROM users
            JOIN role legacy_roles ON legacy_roles.id = users.role_id
            JOIN roles ON roles.name = legacy_roles.name AND roles.guard_name = "web"
            WHERE legacy_roles.name IN ("admin", "client", "super_admin", "technical_support", "panel_user")
        ');
    }

    if (Schema::hasColumn('tickets', 'department_id')) {
        DB::statement('
            UPDATE tickets t
            LEFT JOIN users u ON u.id = t.client_id
            SET t.department_id = u.department_id
            WHERE t.department_id IS NULL
                AND u.department_id IS NOT NULL
        ');
    }

    if (Schema::hasColumn('tickets', 'created_by')) {
        DB::statement('
            UPDATE tickets
            SET created_by = client_id
            WHERE created_by IS NULL
                AND client_id IS NOT NULL
        ');
    }

    DB::statement("
        UPDATE tickets
        SET status = 'on progress'
        WHERE status = 'in progress'
    ");

    DB::statement("
        UPDATE tickets
        SET status = 'closed'
        WHERE status IN ('pending/closed', 'overdue/closed')
    ");

    if (Schema::hasColumn('tickets', 'technical_support_id')) {
        DB::statement('
            INSERT IGNORE INTO ticket_technical_support (ticket_id, user_id, created_at, updated_at)
            SELECT id, CAST(technical_support_id AS UNSIGNED), created_at, updated_at
            FROM tickets
            WHERE technical_support_id IS NOT NULL
                AND technical_support_id != ""
                AND technical_support_id REGEXP "^[0-9]+$"
        ');
    }

    $this->call('permission:cache-reset');

    $this->table(['Check', 'Count'], [
        ['users', DB::table('users')->count()],
        ['department_user', DB::table('department_user')->count()],
        ['ticket_technical_support', DB::table('ticket_technical_support')->count()],
        ['roles', DB::table('roles')->count()],
        ['permissions', DB::table('permissions')->count()],
    ]);

    $this->info('Legacy compatibility seed completed.');

    return self::SUCCESS;
})->purpose('Seed/backfill Shield data and helpdesk-new compatibility tables after importing the legacy dump');
