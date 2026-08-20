<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceAccessSession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides p0 foundation stability test behavior within the WorkIntel application. */ class FoundationStabilityTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test enterprise governance can eager load access session user operation for the current WorkIntel workflow. */ public function test_enterprise_governance_can_eager_load_access_session_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$owner, $member] = $this->userAndMember('owner@acme.test');

        WorkspaceAccessSession::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $member->workspace_id,
            'user_id' => $owner->id,
            'member_id' => $member->id,
            'session_hash' => hash('sha256', 'p0-enterprise-session'),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'P0 Regression Test',
            'last_seen_at' => now(),
            'expires_at' => now()->addHour(),
            'created_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/enterprise/overview', $this->headers($member->workspace_id))
            ->assertOk()
            ->assertJsonPath('sessions.0.user.email', 'owner@acme.test');
    }

    /** Handles the test screenshot policy accepts one minute and returns api limits operation for the current WorkIntel workflow. */ public function test_screenshot_policy_accepts_one_minute_and_returns_api_limits(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);

        $this->getJson('/api/v1/screenshots?date=2026-08-11', $headers)
            ->assertOk()
            ->assertJsonPath('limits.interval_min', 1)
            ->assertJsonPath('limits.interval_max', 60);

        $this->putJson('/api/v1/screenshots/settings', [
            'enabled' => true,
            'interval_minutes' => 1,
            'randomize_minutes' => 0,
            'capture_all_monitors' => true,
            'blur_by_default' => false,
            'quality' => 'medium',
            'allow_employee_delete' => false,
            'retention_days' => 1,
            'max_upload_kb' => 4096,
        ], $headers)->assertOk()->assertJsonPath('settings.interval_minutes', 1);
    }

    /** Handles the test employee can read own task and details without management permissions operation for the current WorkIntel workflow. */ public function test_employee_can_read_own_task_and_details_without_management_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$employee, $member] = $this->userAndMember('employee@acme.test');
        $project = Project::query()->where('workspace_id', $member->workspace_id)->firstOrFail();
        $project->members()->syncWithoutDetaching([$member->id]);
        $task = Task::create([
            'workspace_id' => $member->workspace_id,
            'project_id' => $project->id,
            'title' => 'P0 employee task access',
            'status' => 'todo',
            'priority' => 'medium',
            'billable' => false,
            'created_by' => User::where('email', 'owner@acme.test')->firstOrFail()->id,
        ]);
        $task->assignees()->sync([$member->id]);

        Sanctum::actingAs($employee);
        $headers = $this->headers($member->workspace_id);
        $this->getJson('/api/v1/tasks', $headers)
            ->assertOk()
            ->assertJsonFragment(['title' => 'P0 employee task access']);
        $this->getJson('/api/v1/tasks/'.$task->id.'/details', $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $task->id);
        $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'Employee must not create',
            'status' => 'todo',
            'priority' => 'medium',
            'billable' => false,
        ], $headers)->assertForbidden();
    }

    /** Handles the test primary role journeys do not hit permission contract regressions operation for the current WorkIntel workflow. */ public function test_primary_role_journeys_do_not_hit_permission_contract_regressions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $matrix = [
            'owner@acme.test' => '/api/v1/enterprise/overview',
            'admin@acme.test' => '/api/v1/enterprise/overview',
            'hr@acme.test' => '/api/v1/people',
            'manager@acme.test' => '/api/v1/tasks',
            'teamlead@acme.test' => '/api/v1/tasks',
            'payroll@acme.test' => '/api/v1/payroll/runs',
            'employee@acme.test' => '/api/v1/tasks',
        ];

        foreach ($matrix as $email => $endpoint) {
            [$user, $member] = $this->userAndMember($email);
            Sanctum::actingAs($user);
            $this->getJson($endpoint, $this->headers($member->workspace_id))
                ->assertSuccessful();
        }
    }

    /** Handles the user and member operation for the current WorkIntel workflow. */ private function userAndMember(string $email): array
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        $member = $user->memberships()->where('status', 'active')->firstOrFail();
        return [$user, $member];
    }

    /** Handles the headers operation for the current WorkIntel workflow. */ private function headers(int $workspaceId): array
    {
        return ['X-Workspace-Id' => (string) $workspaceId];
    }
}
