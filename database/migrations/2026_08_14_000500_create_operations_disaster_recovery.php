<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create platform operations, backup, verification and restore-request tables. */
    public function up(): void
    {
        if (! Schema::hasTable('system_backup_policies')) {
            Schema::create('system_backup_policies', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(true);
                $table->string('frequency', 20)->default('daily');
                $table->string('run_time', 5)->default('02:00');
                $table->unsignedInteger('retention_days')->default(14);
                $table->unsignedInteger('minimum_verified_copies')->default(2);
                $table->boolean('include_private_storage')->default(true);
                $table->string('disk', 40)->default('local');
                $table->json('included_paths')->nullable();
                $table->json('excluded_paths')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->foreign('updated_by', 'sys_bkp_policy_user_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('system_backup_runs')) {
            Schema::create('system_backup_runs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('backup_type', 20)->default('full');
                $table->string('status', 20)->default('queued');
                $table->string('database_driver', 20)->nullable();
                $table->string('disk', 40)->default('local');
                $table->string('backup_path', 1000)->nullable();
                $table->string('manifest_path', 1000)->nullable();
                $table->char('sha256', 64)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->unsignedInteger('file_count')->default(0);
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->text('failure_message')->nullable();
                $table->longText('metadata')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('pruned_at')->nullable();
                $table->timestamps();
                $table->foreign('requested_by', 'sys_bkp_run_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['status', 'created_at'], 'sys_bkp_run_status_idx');
                $table->index(['verified_at', 'created_at'], 'sys_bkp_run_verify_idx');
            });
        }

        if (! Schema::hasTable('system_restore_requests')) {
            Schema::create('system_restore_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('backup_run_id')->constrained('system_backup_runs')->cascadeOnDelete();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->char('token_hash', 64)->unique();
                $table->string('status', 20)->default('prepared');
                $table->string('restore_scope', 20)->default('full');
                $table->text('notes')->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('executed_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
                $table->foreign('requested_by', 'sys_restore_req_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['status', 'expires_at'], 'sys_restore_req_status_idx');
            });
        }

        if (! Schema::hasTable('system_operation_events')) {
            Schema::create('system_operation_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('event_type', 80);
                $table->string('severity', 20)->default('info');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('subject_type', 80)->nullable();
                $table->string('subject_id', 100)->nullable();
                $table->text('message');
                $table->longText('metadata')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();
                $table->foreign('actor_user_id', 'sys_op_event_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['event_type', 'occurred_at'], 'sys_op_event_type_idx');
                $table->index(['severity', 'occurred_at'], 'sys_op_event_severity_idx');
            });
        }
    }

    /** Drop platform operations tables in reverse dependency order. */
    public function down(): void
    {
        Schema::dropIfExists('system_operation_events');
        Schema::dropIfExists('system_restore_requests');
        Schema::dropIfExists('system_backup_runs');
        Schema::dropIfExists('system_backup_policies');
    }
};
