<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides approval phase17 flow test behavior within the WorkIntel application. */ class ApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test leave moves through unified two step approval chain operation for the current WorkIntel workflow. */ public function test_leave_moves_through_unified_two_step_approval_chain(): void
    {
        $this->seed(DatabaseSeeder::class);

        $leave = LeaveRequest::query()->where('status', 'pending')->whereHas('member.user', fn ($q) => $q->where('email', 'employee@acme.test'))->firstOrFail();
        $approval = ApprovalRequest::query()->where('subject_type', 'leave_request')->where('subject_id', $leave->id)->where('status', 'pending')->firstOrFail();
        $this->assertSame(1, (int) $approval->current_step_position);

        $manager = User::where('email', 'manager@acme.test')->firstOrFail();
        $managerMember = $manager->memberships()->firstOrFail();
        Sanctum::actingAs($manager);
        $headers = ['X-Workspace-Id' => (string) $managerMember->workspace_id];

        $this->getJson('/api/v1/approvals', $headers)->assertOk()->assertJsonPath('counts.inbox', 1);
        $this->postJson('/api/v1/approvals/'.$approval->id.'/decision', ['decision' => 'approved', 'note' => 'Manager step approved.'], $headers)
            ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.current_step_position', 2);

        $hr = User::where('email', 'hr@acme.test')->firstOrFail();
        $hrMember = $hr->memberships()->firstOrFail();
        Sanctum::actingAs($hr);
        $hrHeaders = ['X-Workspace-Id' => (string) $hrMember->workspace_id];
        $this->getJson('/api/v1/approvals', $hrHeaders)->assertOk()->assertJsonPath('counts.inbox', 1);
        $this->postJson('/api/v1/approvals/'.$approval->id.'/decision', ['decision' => 'approved', 'note' => 'HR approved.'], $hrHeaders)
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertDatabaseHas('approval_decisions', ['approval_request_id' => $approval->id, 'decision' => 'approved', 'actor_member_id' => $managerMember->id]);
        $this->assertDatabaseHas('approval_decisions', ['approval_request_id' => $approval->id, 'decision' => 'approved', 'actor_member_id' => $hrMember->id]);
    }

    /** Handles the test owner can manage workflows and delegations are workspace scoped operation for the current WorkIntel workflow. */ public function test_owner_can_manage_workflows_and_delegations_are_workspace_scoped(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $manager = User::where('email', 'manager@acme.test')->firstOrFail()->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $workflows = $this->getJson('/api/v1/approval-workflows', $headers)->assertOk();
        $this->assertGreaterThanOrEqual(6, count($workflows->json('data')));

        $created = $this->postJson('/api/v1/approval-workflows', [
            'name' => 'High value expense', 'trigger_key' => 'project_expense.submitted', 'description' => 'Finance control',
            'status' => 'active', 'priority' => 10, 'sla_hours' => 8, 'escalation_role_slug' => 'owner', 'notify_requester' => true,
            'conditions' => [['field' => 'amount', 'operator' => 'gte', 'value' => 5000]],
            'steps' => [['name' => 'Owner review', 'approver_type' => 'role', 'approver_role_slug' => 'owner', 'approver_member_id' => null, 'required_approvals' => 1, 'allow_self_approval' => false]],
        ], $headers)->assertCreated();
        $this->assertDatabaseHas('approval_workflows', ['id' => $created->json('data.id'), 'priority' => 10]);

        $this->postJson('/api/v1/approval-delegations', [
            'delegate_member_id' => $manager->id,
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDays(2)->toDateTimeString(),
            'reason' => 'Release week coverage',
        ], $headers)->assertCreated();
        $this->getJson('/api/v1/approval-delegations', $headers)->assertOk()->assertJsonCount(1, 'data');
    }
}
