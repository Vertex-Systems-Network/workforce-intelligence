<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\ExpenseClaim;
use App\Models\ExpensePolicy;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides finance phase20 flow test behavior within the WorkIntel application. */ class FinanceFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test employee expense claim requires receipt then moves through unified approval operation for the current WorkIntel workflow. */ public function test_employee_expense_claim_requires_receipt_then_moves_through_unified_approval(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('email', 'employee@acme.test')->firstOrFail();
        $employeeMember = $employee->memberships()->firstOrFail();
        $workspaceId = $employeeMember->workspace_id;
        $project = Project::where('workspace_id', $workspaceId)->where('status', 'active')->firstOrFail();
        $policy = ExpensePolicy::where('workspace_id', $workspaceId)->where('status', 'active')->firstOrFail();
        $headers = ['X-Workspace-Id' => (string) $workspaceId];

        Sanctum::actingAs($employee);
        $claim = $this->postJson('/api/v1/finance-ops/claims', [
            'title' => 'Release travel expense', 'project_id' => $project->id, 'expense_policy_id' => $policy->id,
            'currency' => $policy->currency, 'note' => 'Phase 20 test claim',
        ], $headers)->assertCreated()->json('data');

        $item = $this->postJson('/api/v1/finance-ops/claims/'.$claim['id'].'/items', [
            'expense_date' => now()->toDateString(), 'category' => 'travel', 'description' => 'Taxi to release workshop',
            'amount' => 35, 'tax_amount' => 0, 'currency' => $policy->currency,
        ], $headers)->assertCreated()->json('data');

        $this->postJson('/api/v1/finance-ops/claims/'.$claim['id'].'/submit', [], $headers)->assertStatus(422);
        $this->post('/api/v1/finance-ops/claims/'.$claim['id'].'/items/'.$item['id'].'/receipt', [
            'receipt' => UploadedFile::fake()->image('taxi-receipt.jpg', 640, 480),
        ], $headers)->assertOk();

        $submitted = $this->postJson('/api/v1/finance-ops/claims/'.$claim['id'].'/submit', [], $headers)
            ->assertOk()->assertJsonPath('data.status', 'submitted');
        $approvalId = $submitted->json('approval_request_id');
        $this->assertNotNull($approvalId);
        $this->assertDatabaseHas('approval_requests', ['id' => $approvalId, 'subject_type' => 'expense_claim', 'status' => 'pending']);

        $manager = User::where('email', 'manager@acme.test')->firstOrFail();
        Sanctum::actingAs($manager);
        $this->postJson('/api/v1/approvals/'.$approvalId.'/decision', ['decision' => 'approved', 'note' => 'Manager approves.'], $headers)
            ->assertOk()->assertJsonPath('data.status', 'pending');

        $admin = User::where('email', 'admin@acme.test')->firstOrFail();
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/approvals/'.$approvalId.'/decision', ['decision' => 'approved', 'note' => 'Finance control approves.'], $headers)
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $approved = ExpenseClaim::findOrFail($claim['id']);
        $this->assertSame('approved', $approved->status);
        $this->assertSame('ready', $approved->reimbursement_status);
        $this->assertSame('35.00', $approved->approved_amount);
    }

    /** Handles the test procurement and job costing are workspace scoped and approval aware operation for the current WorkIntel workflow. */ public function test_procurement_and_job_costing_are_workspace_scoped_and_approval_aware(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $project = Project::where('workspace_id', $membership->workspace_id)->where('status', 'active')->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $request = $this->postJson('/api/v1/finance-ops/purchase-requests', [
            'title' => 'Build test devices', 'vendor' => 'Demo Vendor', 'currency' => $project->currency,
            'amount' => 850, 'project_id' => $project->id, 'needed_by' => now()->addWeek()->toDateString(),
            'justification' => 'Cross-platform release verification.',
        ], $headers)->assertCreated()->json('data');
        $submit = $this->postJson('/api/v1/finance-ops/purchase-requests/'.$request['id'].'/submit', [], $headers)->assertOk();
        $this->assertDatabaseHas('approval_requests', ['id' => $submit->json('approval_request_id'), 'subject_type' => 'purchase_request']);

        $summary = $this->getJson('/api/v1/finance-ops/projects/'.$project->id.'/cost', $headers)->assertOk()->json('data');
        $this->assertArrayHasKey('planned', $summary);
        $this->assertArrayHasKey('actual', $summary);
        $this->assertArrayHasKey('variance', $summary);
        $this->assertArrayHasKey('warnings', $summary);
    }
}
