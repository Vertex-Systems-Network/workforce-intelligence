<?php

namespace Tests\Feature;

use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides payroll flow test behavior within the WorkIntel application. */ class PayrollFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test owner can calculate adjust approve and pay payroll operation for the current WorkIntel workflow. */ public function test_owner_can_calculate_adjust_approve_and_pay_payroll(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $runs = $this->getJson('/api/v1/payroll/runs', $headers)
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($runs);
        $runId = $runs[0]['id'];

        $detail = $this->getJson('/api/v1/payroll/runs/'.$runId, $headers)
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($detail['items']);
        $itemId = $detail['items'][0]['id'];
        $before = (float) $detail['items'][0]['net_pay'];

        $adjusted = $this->postJson('/api/v1/payroll/items/'.$itemId.'/adjustments', [
            'category' => 'bonus',
            'direction' => 'earning',
            'label' => 'Test bonus',
            'amount' => 125,
        ], $headers)->assertOk()->json('data');

        $this->assertGreaterThan($before, (float) $adjusted['net_pay']);

        $this->postJson('/api/v1/payroll/runs/'.$runId.'/submit', [], $headers)
            ->assertOk()->assertJsonPath('data.status', 'review');

        $this->postJson('/api/v1/payroll/runs/'.$runId.'/approve', [], $headers)
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertNotNull(PayrollRun::findOrFail($runId)->locked_at);

        $this->postJson('/api/v1/payroll/items/'.$itemId.'/adjustments', [
            'category' => 'bonus', 'direction' => 'earning', 'label' => 'Too late', 'amount' => 10,
        ], $headers)->assertStatus(422);

        $this->postJson('/api/v1/payroll/runs/'.$runId.'/mark-paid', [], $headers)
            ->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertSame('paid', PayrollItem::findOrFail($itemId)->status);
    }

    /** Handles the test employee can only read approved or paid own payroll operation for the current WorkIntel workflow. */ public function test_employee_can_only_read_approved_or_paid_own_payroll(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $ownerMembership = $owner->memberships()->firstOrFail();
        $run = PayrollRun::where('workspace_id', $ownerMembership->workspace_id)->firstOrFail();
        $run->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $owner->id, 'locked_at' => now(), 'locked_by' => $owner->id]);
        $run->items()->update(['status' => 'approved']);

        $employee = User::where('email', 'employee@acme.test')->firstOrFail();
        $employeeMembership = $employee->memberships()->firstOrFail();
        Sanctum::actingAs($employee);
        $headers = ['X-Workspace-Id' => (string) $employeeMembership->workspace_id];

        $response = $this->getJson('/api/v1/payroll/me', $headers)->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->getJson('/api/v1/payroll/runs', $headers)->assertForbidden();
    }
}
