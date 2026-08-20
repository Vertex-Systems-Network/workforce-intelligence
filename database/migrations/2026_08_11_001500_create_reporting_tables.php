<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('saved_reports')) {
            Schema::create('saved_reports', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->string('dataset', 40);
                $table->json('configuration');
                $table->boolean('is_shared')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'dataset']);
                $table->index(['workspace_id', 'updated_at']);
            });
        }

        if (! Schema::hasTable('report_schedules')) {
            Schema::create('report_schedules', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('saved_report_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 160);
                $table->string('frequency', 20); // daily, weekly, monthly
                $table->string('time_of_day', 5)->default('08:00');
                $table->unsignedTinyInteger('day_of_week')->nullable(); // 0 (Sun) - 6 (Sat)
                $table->unsignedTinyInteger('day_of_month')->nullable(); // 1 - 28
                $table->string('timezone', 64)->default('UTC');
                $table->string('export_format', 12)->default('pdf');
                $table->boolean('active')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'active', 'next_run_at']);
            });
        }

        if (! Schema::hasTable('report_runs')) {
            Schema::create('report_runs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('saved_report_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('report_schedule_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 160);
                $table->string('dataset', 40);
                $table->json('configuration');
                $table->string('status', 20)->default('pending'); // pending, running, completed, failed
                $table->unsignedInteger('row_count')->default(0);
                $table->json('columns')->nullable();
                $table->longText('result_rows')->nullable();
                $table->json('summary')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'created_at']);
                $table->index(['workspace_id', 'status']);
            });
        }

        if (! Schema::hasTable('report_exports')) {
            Schema::create('report_exports', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('report_run_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('format', 12); // csv, xlsx, pdf
                $table->string('disk', 40)->default('local');
                $table->string('path', 500)->nullable();
                $table->string('filename', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->string('status', 20)->default('pending');
                $table->timestamp('completed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'created_at']);
            });
        }

        // Existing installations may already have these system roles. Reporting managers
        // need report builder/schedule access without requiring demo seeders in production.
        $managePermissionId = DB::table('permissions')->where('slug', 'reports.manage')->value('id');
        if ($managePermissionId) {
            DB::table('roles')->whereIn('slug', ['owner', 'admin', 'hr', 'manager', 'payroll-manager'])->pluck('id')->each(function ($roleId) use ($managePermissionId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $managePermissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('saved_reports');
    }
};
