<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Provides payroll compliance doctor behavior within the WorkIntel application. */ class PayrollComplianceDoctor extends Command
{
    protected $signature = 'workintel:payroll-compliance-doctor';
    protected $description = 'Validate Phase 21 payroll compliance schema and storage';

    /** Executes the command, job, or request handler. */ public function handle(): int
    {
        $tables = [
            'payroll_compliance_packs', 'payroll_compliance_rules', 'member_payroll_assignments',
            'member_benefits', 'payroll_item_compliance_lines', 'payroll_run_members',
            'contractor_payment_profiles', 'retro_pay_adjustments', 'termination_settlements', 'payroll_exports',
        ];
        $bad = 0;
        foreach ($tables as $table) {
            $ok = Schema::hasTable($table);
            $this->line(($ok ? '[OK] ' : '[MISSING] ').$table);
            if (! $ok) $bad++;
        }

        foreach (['run_type', 'compliance_pack_id'] as $column) {
            $ok = Schema::hasColumn('payroll_runs', $column);
            $this->line(($ok ? '[OK] ' : '[MISSING] ').'payroll_runs.'.$column);
            if (! $ok) $bad++;
        }
        foreach (['statutory_deduction_total', 'employer_contribution_total', 'benefit_total', 'allowance_total'] as $column) {
            $ok = Schema::hasColumn('payroll_items', $column);
            $this->line(($ok ? '[OK] ' : '[MISSING] ').'payroll_items.'.$column);
            if (! $ok) $bad++;
        }

        return $bad ? self::FAILURE : self::SUCCESS;
    }
}
