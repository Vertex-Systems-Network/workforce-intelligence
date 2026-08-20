<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        foreach ([
            ['Payroll Compliance', 'payroll.compliance.view'],
            ['Payroll Compliance', 'payroll.compliance.manage'],
            ['Payroll Compliance', 'payroll.exports.manage'],
            ['Payroll Compliance', 'payroll.contractors.manage'],
        ] as [$group, $slug]) {
            if (Schema::hasTable('permissions')) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
                );
            }
        }

        if (Schema::hasTable('roles') && Schema::hasTable('permissions') && Schema::hasTable('role_permissions')) {
            $ids = DB::table('permissions')->pluck('id', 'slug');
            $timestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');
            $grant = function (string $roleSlug, array $slugs) use ($ids, $timestamps): void {
                foreach (DB::table('roles')->where('is_system', true)->where('slug', $roleSlug)->get(['id']) as $role) {
                    foreach ($slugs as $slug) {
                        $permissionId = $ids[$slug] ?? null;
                        if (! $permissionId) continue;
                        $row = ['role_id' => $role->id, 'permission_id' => $permissionId];
                        if ($timestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                        DB::table('role_permissions')->insertOrIgnore($row);
                    }
                }
            };
            foreach (['owner', 'admin', 'payroll-manager'] as $role) {
                $grant($role, ['payroll.compliance.view', 'payroll.compliance.manage', 'payroll.exports.manage', 'payroll.contractors.manage']);
            }
            $grant('hr', ['payroll.compliance.view']);
        }

        if (! Schema::hasTable('payroll_compliance_packs')) {
            Schema::create('payroll_compliance_packs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 160);
                $table->char('country_code', 2)->nullable();
                $table->string('region_code', 32)->nullable();
                $table->string('version', 40)->default('1.0');
                $table->char('currency', 3);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('status', 20)->default('draft');
                $table->boolean('replace_default_tax')->default(true);
                $table->json('settings')->nullable();
                $table->text('disclaimer')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'status', 'effective_from'], 'pcp_ws_status_effective_idx');
            });
        }

        if (! Schema::hasTable('payroll_compliance_rules')) {
            Schema::create('payroll_compliance_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_compliance_pack_id')->constrained('payroll_compliance_packs')->cascadeOnDelete();
                $table->string('code', 60);
                $table->string('name', 160);
                $table->string('category', 32); // tax, statutory_deduction, employer_contribution, allowance, benefit, termination
                $table->string('calculation_type', 24)->default('percentage'); // percentage, fixed, brackets
                $table->string('basis', 24)->default('gross'); // base, gross, taxable_gross, fixed
                $table->decimal('rate_percent', 8, 4)->nullable();
                $table->decimal('employer_rate_percent', 8, 4)->nullable();
                $table->decimal('fixed_amount', 14, 2)->nullable();
                $table->decimal('employer_fixed_amount', 14, 2)->nullable();
                $table->decimal('minimum_basis', 14, 2)->nullable();
                $table->decimal('maximum_basis', 14, 2)->nullable();
                $table->decimal('employee_cap', 14, 2)->nullable();
                $table->decimal('employer_cap', 14, 2)->nullable();
                $table->boolean('taxable')->default(false);
                $table->boolean('affects_gross')->default(false);
                $table->boolean('active')->default(true);
                $table->json('brackets')->nullable();
                $table->json('conditions')->nullable();
                $table->unsignedSmallInteger('priority')->default(100);
                $table->timestamps();
                $table->unique(['payroll_compliance_pack_id', 'code'], 'pcr_pack_code_uq');
                $table->index(['payroll_compliance_pack_id', 'active', 'priority'], 'pcr_pack_active_priority_idx');
            });
        }

        if (! Schema::hasTable('member_payroll_assignments')) {
            Schema::create('member_payroll_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('payroll_compliance_pack_id')->nullable()->constrained('payroll_compliance_packs')->nullOnDelete();
                $table->string('worker_classification', 24)->default('employee'); // employee, contractor
                $table->string('tax_identifier', 120)->nullable();
                $table->string('residency_status', 60)->nullable();
                $table->json('exemptions')->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
                $table->index(['workspace_id', 'member_id', 'effective_from'], 'mpa_ws_member_effective_idx');
            });
        }

        if (! Schema::hasTable('member_benefits')) {
            Schema::create('member_benefits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('code', 60);
                $table->string('name', 160);
                $table->string('type', 24); // allowance, benefit, deduction
                $table->decimal('employee_amount', 14, 2)->default(0);
                $table->decimal('employer_amount', 14, 2)->default(0);
                $table->string('frequency', 20)->default('payroll');
                $table->boolean('taxable')->default(false);
                $table->boolean('cash')->default(true);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('status', 20)->default('active');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'member_id', 'status'], 'mb_ws_member_status_idx');
            });
        }

        if (! Schema::hasTable('payroll_item_compliance_lines')) {
            Schema::create('payroll_item_compliance_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('payroll_compliance_rule_id')->nullable()->constrained('payroll_compliance_rules')->nullOnDelete();
                $table->string('code', 60);
                $table->string('name', 160);
                $table->string('category', 32);
                $table->decimal('basis_amount', 14, 2)->default(0);
                $table->decimal('employee_amount', 14, 2)->default(0);
                $table->decimal('employer_amount', 14, 2)->default(0);
                $table->boolean('affects_gross')->default(false);
                $table->boolean('taxable')->default(false);
                $table->json('rule_snapshot')->nullable();
                $table->timestamps();
                $table->unique(['payroll_item_id', 'code'], 'picl_item_code_uq');
                $table->index(['workspace_id', 'category'], 'picl_ws_category_idx');
            });
        }

        if (! Schema::hasTable('payroll_run_members')) {
            Schema::create('payroll_run_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['payroll_run_id', 'member_id'], 'prm_run_member_uq');
            });
        }

        if (! Schema::hasTable('contractor_payment_profiles')) {
            Schema::create('contractor_payment_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->unique()->constrained('workspace_members')->cascadeOnDelete();
                $table->string('vendor_name', 180)->nullable();
                $table->string('tax_identifier', 120)->nullable();
                $table->string('payment_terms', 60)->nullable();
                $table->string('payment_method', 40)->nullable();
                $table->json('bank_reference')->nullable();
                $table->boolean('withholding_enabled')->default(false);
                $table->decimal('withholding_percent', 8, 4)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('retro_pay_adjustments')) {
            Schema::create('retro_pay_adjustments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->char('currency', 3);
                $table->decimal('amount', 14, 2);
                $table->date('source_period_start');
                $table->date('source_period_end');
                $table->string('reason', 500);
                $table->string('status', 20)->default('pending');
                $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
                $table->foreignId('payroll_adjustment_id')->nullable()->constrained('payroll_adjustments')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'member_id', 'status'], 'rpa_ws_member_status_idx');
            });
        }

        if (! Schema::hasTable('termination_settlements')) {
            Schema::create('termination_settlements', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('payroll_compliance_pack_id')->nullable()->constrained('payroll_compliance_packs')->nullOnDelete();
                $table->char('currency', 3);
                $table->date('termination_date');
                $table->decimal('service_years', 8, 3)->default(0);
                $table->decimal('base_amount', 14, 2)->default(0);
                $table->decimal('leave_payout', 14, 2)->default(0);
                $table->decimal('other_earnings', 14, 2)->default(0);
                $table->decimal('deductions', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->string('status', 20)->default('draft');
                $table->json('calculation_snapshot')->nullable();
                $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'member_id', 'status'], 'ts_ws_member_status_idx');
            });
        }

        if (! Schema::hasTable('payroll_exports')) {
            Schema::create('payroll_exports', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 60)->default('generic');
                $table->string('format', 20)->default('csv');
                $table->string('file_path', 500);
                $table->string('file_name', 255);
                $table->char('sha256', 64);
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'payroll_run_id', 'created_at'], 'pex_ws_run_created_idx');
            });
        }

        if (Schema::hasTable('payroll_runs')) {
            if (! Schema::hasColumn('payroll_runs', 'run_type')) Schema::table('payroll_runs', fn (Blueprint $table) => $table->string('run_type', 24)->default('regular')->after('currency'));
            if (! Schema::hasColumn('payroll_runs', 'compliance_pack_id')) Schema::table('payroll_runs', fn (Blueprint $table) => $table->foreignId('compliance_pack_id')->nullable()->after('run_type')->constrained('payroll_compliance_packs')->nullOnDelete());
        }
        if (Schema::hasTable('payroll_items')) {
            foreach ([
                'statutory_deduction_total' => fn (Blueprint $table) => $table->decimal('statutory_deduction_total', 14, 2)->default(0)->after('deduction_total'),
                'employer_contribution_total' => fn (Blueprint $table) => $table->decimal('employer_contribution_total', 14, 2)->default(0)->after('statutory_deduction_total'),
                'benefit_total' => fn (Blueprint $table) => $table->decimal('benefit_total', 14, 2)->default(0)->after('employer_contribution_total'),
                'allowance_total' => fn (Blueprint $table) => $table->decimal('allowance_total', 14, 2)->default(0)->after('benefit_total'),
            ] as $column => $add) if (! Schema::hasColumn('payroll_items', $column)) Schema::table('payroll_items', $add);
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('payroll_exports');
        Schema::dropIfExists('termination_settlements');
        Schema::dropIfExists('retro_pay_adjustments');
        Schema::dropIfExists('contractor_payment_profiles');
        Schema::dropIfExists('payroll_run_members');
        Schema::dropIfExists('payroll_item_compliance_lines');
        Schema::dropIfExists('member_benefits');
        Schema::dropIfExists('member_payroll_assignments');
        Schema::dropIfExists('payroll_compliance_rules');
        Schema::dropIfExists('payroll_compliance_packs');
    }
};
