<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('task_dependencies')) {
            Schema::create('task_dependencies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
                $table->string('type', 32)->default('finish_to_start');
                $table->timestamps();
                $table->unique(['task_id', 'depends_on_task_id']);
                $table->index(['workspace_id', 'task_id']);
            });
        }

        if (! Schema::hasTable('task_recurrences')) {
            Schema::create('task_recurrences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('task_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('frequency', 24);
                $table->unsignedSmallInteger('interval')->default(1);
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
                $table->timestamp('next_run_at');
                $table->timestamp('last_generated_at')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->index(['workspace_id', 'active', 'next_run_at']);
            });
        }

        if (! Schema::hasColumn('tasks', 'recurrence_template_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->foreignId('recurrence_template_id')->nullable()->after('parent_id')->constrained('tasks')->nullOnDelete();
                $table->index(['workspace_id', 'recurrence_template_id']);
            });
        }

        if (! Schema::hasTable('attendance_breaks')) {
            Schema::create('attendance_breaks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('type', 24)->default('break');
                $table->boolean('paid')->default(false);
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('duration_seconds')->default(0);
                $table->string('note', 500)->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'member_id', 'started_at']);
                $table->index(['attendance_record_id', 'ended_at']);
            });
        }

        if (! Schema::hasTable('holidays')) {
            Schema::create('holidays', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 140);
                $table->date('date');
                $table->string('type', 24)->default('public');
                $table->boolean('paid')->default(true);
                $table->string('status', 20)->default('active');
                $table->timestamps();
                $table->unique(['workspace_id', 'date', 'name']);
                $table->index(['workspace_id', 'date']);
            });
        }

        if (! Schema::hasTable('leave_policies')) {
            Schema::create('leave_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('leave_type_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('accrual_method', 24)->default('annual');
                $table->decimal('monthly_accrual_days', 6, 2)->default(0);
                $table->decimal('carryover_days', 6, 2)->default(0);
                $table->unsignedSmallInteger('min_notice_days')->default(0);
                $table->unsignedSmallInteger('max_consecutive_days')->nullable();
                $table->unsignedSmallInteger('probation_months')->default(0);
                $table->boolean('allow_negative_balance')->default(false);
                $table->boolean('requires_approval')->default(true);
                $table->boolean('exclude_weekends')->default(true);
                $table->boolean('exclude_holidays')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('leave_balances')) {
            Schema::create('leave_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('year');
                $table->decimal('opening_days', 7, 2)->default(0);
                $table->decimal('carried_days', 7, 2)->default(0);
                $table->decimal('accrued_days', 7, 2)->default(0);
                $table->decimal('adjustment_days', 7, 2)->default(0);
                $table->decimal('used_days', 7, 2)->default(0);
                $table->timestamps();
                $table->unique(['workspace_id', 'member_id', 'leave_type_id', 'year'], 'leave_balance_unique');
            });
        }

        if (! Schema::hasTable('timesheet_actions')) {
            Schema::create('timesheet_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('timesheet_period_id')->nullable()->constrained('timesheet_periods')->cascadeOnDelete();
                $table->foreignId('time_entry_id')->nullable()->constrained('time_entries')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 32);
                $table->string('previous_status', 24)->nullable();
                $table->string('new_status', 24)->nullable();
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'member_id', 'created_at']);
                $table->index(['timesheet_period_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('project_expenses')) {
            Schema::create('project_expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->string('name', 160);
                $table->string('category', 60)->default('other');
                $table->decimal('amount', 14, 2);
                $table->char('currency', 3);
                $table->date('incurred_on');
                $table->text('note')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'project_id', 'incurred_on']);
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('project_expenses');
        Schema::dropIfExists('timesheet_actions');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('attendance_breaks');

        if (Schema::hasColumn('tasks', 'recurrence_template_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropForeign(['recurrence_template_id']);
                $table->dropIndex(['workspace_id', 'recurrence_template_id']);
                $table->dropColumn('recurrence_template_id');
            });
        }

        Schema::dropIfExists('task_recurrences');
        Schema::dropIfExists('task_dependencies');
    }
};
