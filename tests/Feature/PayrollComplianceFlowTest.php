<?php

namespace Tests\Feature;

use App\Models\PayrollExport;
use App\Models\PayrollItemComplianceLine;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides payroll compliance phase21 flow test behavior within the WorkIntel application. */ class PayrollComplianceFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test compliance pack snapshots statutory lines and private export operation for the current WorkIntel workflow. */ public function test_compliance_pack_snapshots_statutory_lines_and_private_export(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $member = $owner->memberships()->with('workspace')->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $member->workspace_id];

        $pack = $this->postJson('/api/v1/payroll-compliance/packs', [
            'name' => 'Phase 21 Test Pack',
            'version' => '2026.test',
            'currency' => $member->workspace->currency,
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'replace_default_tax' => false,
            'rules' => [[
                'code' => 'TEST-STAT-10',
                'name' => 'Test statutory contribution',
                'category' => 'statutory_deduction',
                'calculation_type' => 'percentage',
                'basis' => 'gross',
                'rate_percent' => 10,
                'employer_rate_percent' => 5,
                'active' => true,
                'priority' => 10,
            ]],
        ], $headers)->assertCreated()->json('data');

        $run = PayrollRun::where('workspace_id', $member->workspace_id)->firstOrFail();
        $run->update([
            'compliance_pack_id' => $pack['id'],
            'status' => 'draft',
            'locked_at' => null,
            'locked_by' => null,
            'approved_at' => null,
            'approved_by' => null,
            'paid_at' => null,
            'paid_by' => null,
        ]);

        $this->postJson('/api/v1/payroll/runs/'.$run->id.'/calculate', [], $headers)->assertOk();

        $line = PayrollItemComplianceLine::where('workspace_id', $member->workspace_id)
            ->where('code', 'TEST-STAT-10')->firstOrFail();
        $this->assertGreaterThan(0, (float) $line->employee_amount);
        $this->assertGreaterThan(0, (float) $line->employer_amount);
        $this->assertSame('2026.test', data_get($line->rule_snapshot, 'pack.version'));
        $this->assertSame(10.0, (float) data_get($line->rule_snapshot, 'rule.rate_percent'));

        $export = $this->postJson('/api/v1/payroll-compliance/runs/'.$run->id.'/exports', [
            'provider' => 'accounting-test', 'format' => 'csv',
        ], $headers)->assertCreated()->json('data');

        $stored = PayrollExport::findOrFail($export['id']);
        Storage::disk('local')->assertExists($stored->file_path);
        $this->assertNotEmpty($stored->sha256);
    }

    /** Handles the test non regular run requires explicit members operation for the current WorkIntel workflow. */ public function test_non_regular_run_requires_explicit_members(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->with('workspace')->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $this->postJson('/api/v1/payroll/runs', [
            'name' => 'Off-cycle without members',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-05',
            'pay_date' => '2026-09-06',
            'currency' => $membership->workspace->currency,
            'run_type' => 'off_cycle',
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('member_ids');
    }
}
