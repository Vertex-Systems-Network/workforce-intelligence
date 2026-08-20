<?php

namespace Database\Seeders;

use App\Models\MemberBenefit;
use App\Models\MemberPayrollAssignment;
use App\Models\PayrollCompliancePack;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Provides phase21 payroll compliance seeder behavior within the WorkIntel application. */ class PayrollComplianceSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        if (! Schema::hasTable('payroll_compliance_packs')) return;
        $workspace = Workspace::where('slug', 'acme-corp')->first();
        $owner = User::where('email', 'owner@acme.test')->first();
        $employee = User::where('email', 'employee@acme.test')->first();
        if (! $workspace || ! $owner || ! $employee) return;
        $member = WorkspaceMember::where('workspace_id', $workspace->id)->where('user_id', $employee->id)->first();
        if (! $member) return;

        $pack = PayrollCompliancePack::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Generic Demo Compliance Pack', 'version' => '2026.1'],
            [
                'uuid' => (string) Str::uuid(),
                'country_code' => null,
                'region_code' => null,
                'currency' => $workspace->currency ?: 'USD',
                'effective_from' => '2026-01-01',
                'status' => 'draft',
                'replace_default_tax' => false,
                'settings' => ['termination_days_per_service_year' => 15],
                'disclaimer' => 'Demonstration rules only. Replace with reviewed jurisdiction-specific payroll configuration before production use.',
                'created_by' => $owner->id,
            ]
        );

        if ($pack->rules()->count() === 0) {
            $pack->rules()->create([
                'code' => 'DEMO-EMP-CONTRIB', 'name' => 'Demo employee contribution', 'category' => 'statutory_deduction',
                'calculation_type' => 'percentage', 'basis' => 'gross', 'rate_percent' => 2, 'employer_rate_percent' => 3,
                'taxable' => false, 'affects_gross' => false, 'active' => true, 'priority' => 10,
            ]);
            $pack->rules()->create([
                'code' => 'DEMO-WITHHOLD', 'name' => 'Demo withholding band', 'category' => 'tax',
                'calculation_type' => 'brackets', 'basis' => 'taxable_gross', 'brackets' => [
                    ['up_to' => 2000, 'rate_percent' => 0], ['up_to' => 5000, 'rate_percent' => 5], ['up_to' => null, 'rate_percent' => 10],
                ],
                'taxable' => false, 'affects_gross' => false, 'active' => true, 'priority' => 20,
            ]);
        }

        // Seed assignment is intentionally inactive so demo compliance data does
        // not alter legacy payroll regression expectations until explicitly enabled.
        MemberPayrollAssignment::firstOrCreate(
            ['workspace_id' => $workspace->id, 'member_id' => $member->id, 'effective_from' => '2026-01-01'],
            ['payroll_compliance_pack_id' => $pack->id, 'worker_classification' => 'employee', 'residency_status' => 'resident', 'status' => 'inactive']
        );
        MemberBenefit::firstOrCreate(
            ['workspace_id' => $workspace->id, 'member_id' => $member->id, 'code' => 'DEMO-ALLOW'],
            ['name' => 'Demo monthly allowance', 'type' => 'allowance', 'employee_amount' => 150, 'employer_amount' => 0, 'frequency' => 'monthly', 'taxable' => true, 'cash' => true, 'effective_from' => '2026-01-01', 'status' => 'inactive']
        );
    }
}
