<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        // Older packages may already contain projects.completed_at. Keep this
        // migration retry-safe when upgrading or recovering from a partial run.
        if (! Schema::hasColumn('projects', 'completed_at')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->timestamp('completed_at')->nullable()->after('due_date');
            });
        }

        if (! Schema::hasTable('compensation_profiles')) {
            Schema::create('compensation_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('pay_type', 24); // hourly, daily, monthly, yearly, project
                $table->char('currency', 3);
                $table->decimal('hourly_rate', 14, 2)->nullable();
                $table->decimal('daily_rate', 14, 2)->nullable();
                $table->decimal('monthly_salary', 14, 2)->nullable();
                $table->decimal('annual_salary', 14, 2)->nullable();
                $table->decimal('project_rate', 14, 2)->nullable();
                $table->decimal('premium_hourly_rate', 14, 2)->nullable();
                $table->decimal('standard_hours_per_day', 5, 2)->default(8);
                $table->decimal('standard_hours_per_week', 5, 2)->default(40);
                $table->decimal('overtime_multiplier', 5, 2)->default(1.50);
                $table->decimal('weekend_multiplier', 5, 2)->default(1.50);
                $table->decimal('holiday_multiplier', 5, 2)->default(2.00);
                $table->decimal('default_tax_percent', 5, 2)->default(0);
                $table->boolean('deduct_unpaid_leave')->default(true);
                $table->string('proration_mode', 24)->default('calendar_days');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('status', 20)->default('active');
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'member_id', 'effective_from'], 'cp_ws_member_effective_idx');
                $table->index(['workspace_id', 'status']);
            });
        }


        // MySQL limits identifiers (including index names) to 64 characters.
        // Older builds could partially create compensation_profiles and then
        // fail while adding Laravel's auto-generated 65-character index name.
        // Repair that partial state without dropping data, and keep both
        // important lookup indexes present on fresh and recovered installs.
        if (Schema::hasTable('compensation_profiles')) {
            if (! Schema::hasIndex('compensation_profiles', ['workspace_id', 'member_id', 'effective_from'])) {
                Schema::table('compensation_profiles', function (Blueprint $table) {
                    $table->index(['workspace_id', 'member_id', 'effective_from'], 'cp_ws_member_effective_idx');
                });
            }

            if (! Schema::hasIndex('compensation_profiles', ['workspace_id', 'status'])) {
                Schema::table('compensation_profiles', function (Blueprint $table) {
                    $table->index(['workspace_id', 'status'], 'cp_ws_status_idx');
                });
            }
        }

        if (! Schema::hasTable('payroll_runs')) {
            Schema::create('payroll_runs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 160);
                $table->date('period_start');
                $table->date('period_end');
                $table->date('pay_date')->nullable();
                $table->char('currency', 3);
                $table->string('status', 24)->default('draft');
                $table->timestamp('calculated_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'period_start', 'period_end']);
                $table->index(['workspace_id', 'status']);
            });
        }

        if (! Schema::hasTable('payroll_items')) {
            Schema::create('payroll_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('compensation_profile_id')->nullable()->constrained()->nullOnDelete();
                $table->string('pay_type', 24);
                $table->char('currency', 3);
                $table->json('rate_snapshot')->nullable();
                $table->unsignedInteger('tracked_seconds')->default(0);
                $table->unsignedInteger('regular_seconds')->default(0);
                $table->unsignedInteger('overtime_seconds')->default(0);
                $table->unsignedInteger('weekend_seconds')->default(0);
                $table->unsignedInteger('holiday_seconds')->default(0);
                $table->decimal('attendance_days', 7, 2)->default(0);
                $table->decimal('unpaid_leave_days', 7, 2)->default(0);
                $table->unsignedSmallInteger('project_units')->default(0);
                $table->decimal('base_pay', 14, 2)->default(0);
                $table->decimal('overtime_pay', 14, 2)->default(0);
                $table->decimal('weekend_pay', 14, 2)->default(0);
                $table->decimal('holiday_pay', 14, 2)->default(0);
                $table->decimal('unpaid_leave_deduction', 14, 2)->default(0);
                $table->decimal('bonus_total', 14, 2)->default(0);
                $table->decimal('commission_total', 14, 2)->default(0);
                $table->decimal('reimbursement_total', 14, 2)->default(0);
                $table->decimal('deduction_total', 14, 2)->default(0);
                $table->decimal('tax_total', 14, 2)->default(0);
                $table->decimal('adjustment_total', 14, 2)->default(0);
                $table->decimal('gross_pay', 14, 2)->default(0);
                $table->decimal('net_pay', 14, 2)->default(0);
                $table->string('status', 24)->default('calculated');
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['payroll_run_id', 'member_id']);
                $table->index(['workspace_id', 'member_id']);
            });
        }

        if (! Schema::hasTable('payroll_adjustments')) {
            Schema::create('payroll_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('category', 24); // bonus, commission, deduction, tax, reimbursement, advance, adjustment
                $table->string('direction', 12); // earning, deduction
                $table->string('label', 160);
                $table->decimal('amount', 14, 2);
                $table->text('note')->nullable();
                $table->string('source', 24)->default('manual');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payroll_item_projects')) {
            Schema::create('payroll_item_projects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 14, 2);
                $table->timestamps();
                $table->unique(['payroll_item_id', 'project_id']);
                $table->index(['workspace_id', 'member_id', 'project_id']);
            });
        }

        if (! Schema::hasTable('payroll_actions')) {
            Schema::create('payroll_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
                $table->foreignId('payroll_item_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 40);
                $table->string('from_status', 24)->nullable();
                $table->string('to_status', 24)->nullable();
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();
                $table->index(['workspace_id', 'payroll_run_id', 'occurred_at']);
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('payroll_actions');
        Schema::dropIfExists('payroll_item_projects');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('compensation_profiles');

    }
};
