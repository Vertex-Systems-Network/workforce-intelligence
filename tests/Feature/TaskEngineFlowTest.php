<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkspaceMember;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides p5 task engine flow test behavior within the WorkIntel application. */ class TaskEngineFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the set up operation for the current WorkIntel workflow. */ protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Handles the test owner can use custom workflow multi assignees observers tags and board transitions operation for the current WorkIntel workflow. */ public function test_owner_can_use_custom_workflow_multi_assignees_observers_tags_and_board_transitions(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        [, $manager] = $this->userAndMember('manager@acme.test');
        [$employeeUser, $employee] = $this->userAndMember('employee@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($ownerMember->workspace_id);
        $project = Project::where('workspace_id', $ownerMember->workspace_id)->firstOrFail();

        $workflow = $this->getJson('/api/v1/task-workflow', $headers)->assertOk()->json();
        $this->assertGreaterThanOrEqual(5, count($workflow['statuses']));
        $todo = collect($workflow['statuses'])->firstWhere('slug', 'todo');
        $done = collect($workflow['statuses'])->firstWhere('slug', 'done');

        $qa = $this->postJson('/api/v1/task-workflow/statuses', [
            'name' => 'Quality Assurance', 'color' => '#7c3aed', 'group' => 'review',
        ], $headers)->assertCreated()->json('data');
        $tag = $this->postJson('/api/v1/task-workflow/tags', [
            'name' => 'Release', 'color' => '#2563eb',
        ], $headers)->assertCreated()->json('data');

        $task = $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id,
            'title' => 'P5 workflow task',
            'description_html' => '<h2>Acceptance</h2><p>Ship <strong>safely</strong>.</p><script>alert(1)</script>',
            'status_id' => $todo['id'],
            'priority' => 'high',
            'billable' => true,
            'client_visible' => false,
            'owner_member_id' => $manager->id,
            'assignee_ids' => [$manager->id],
            'observer_ids' => [$employee->id],
            'tag_ids' => [$tag['id']],
        ], $headers)->assertCreated()->json('data');

        $this->assertSame([$manager->id], collect($task['assignees'])->pluck('id')->all());
        $this->assertSame([$employee->id], collect($task['observers'])->pluck('id')->all());
        $this->assertSame([$tag['id']], collect($task['tags'])->pluck('id')->all());
        $this->assertStringNotContainsString('<script', (string) $task['description_html']);

        // Once outgoing rules exist, only explicitly allowed next statuses are valid.
        $this->putJson('/api/v1/task-workflow/statuses/'.$todo['id'].'/transitions', [
            'to_status_ids' => [$qa['id']],
        ], $headers)->assertOk();
        $this->patchJson('/api/v1/tasks/'.$task['id'].'/move', ['status_id' => $done['id']], $headers)
            ->assertUnprocessable();
        $this->patchJson('/api/v1/tasks/'.$task['id'].'/move', ['status_id' => $qa['id']], $headers)
            ->assertOk()->assertJsonPath('data.task_status_id', $qa['id']);

        // Observer visibility is a first-class task scope.
        Sanctum::actingAs($employeeUser);
        $this->getJson('/api/v1/tasks/'.$task['id'].'/details', $headers)
            ->assertOk()->assertJsonPath('data.title', 'P5 workflow task');
    }

    /** Handles the test visible employee can complete checklist but cannot manage task structure operation for the current WorkIntel workflow. */ public function test_visible_employee_can_complete_checklist_but_cannot_manage_task_structure(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        [$employeeUser, $employee] = $this->userAndMember('employee@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($ownerMember->workspace_id);
        $project = Project::where('workspace_id', $ownerMember->workspace_id)->firstOrFail();
        $todo = TaskStatus::where('workspace_id', $ownerMember->workspace_id)->where('slug', 'todo')->firstOrFail();
        $task = $this->postJson('/api/v1/tasks', [
            'project_id' => $project->id, 'title' => 'Checklist task', 'status_id' => $todo->id,
            'priority' => 'medium', 'billable' => false, 'client_visible' => false,
            'assignee_ids' => [$employee->id], 'observer_ids' => [], 'tag_ids' => [],
        ], $headers)->assertCreated()->json('data');
        $item = $this->postJson('/api/v1/tasks/'.$task['id'].'/checklist', ['title' => 'Verify output'], $headers)
            ->assertCreated()->json('data');

        Sanctum::actingAs($employeeUser);
        $this->putJson('/api/v1/tasks/'.$task['id'].'/checklist/'.$item['id'], ['is_completed' => true], $headers)
            ->assertOk()->assertJsonPath('data.is_completed', true);
        $this->postJson('/api/v1/tasks/'.$task['id'].'/checklist', ['title' => 'Not allowed'], $headers)->assertForbidden();
        $this->patchJson('/api/v1/tasks/'.$task['id'].'/move', ['status_id' => $todo->id], $headers)->assertForbidden();
    }

    /** Handles the test parent cycles are rejected and activity history is persisted operation for the current WorkIntel workflow. */ public function test_parent_cycles_are_rejected_and_activity_history_is_persisted(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($ownerMember->workspace_id);
        $project = Project::where('workspace_id', $ownerMember->workspace_id)->firstOrFail();
        $todo = TaskStatus::where('workspace_id', $ownerMember->workspace_id)->where('slug', 'todo')->firstOrFail();
        $base = ['project_id'=>$project->id,'status_id'=>$todo->id,'priority'=>'medium','billable'=>false,'client_visible'=>false,'assignee_ids'=>[],'observer_ids'=>[],'tag_ids'=>[]];
        $parent = $this->postJson('/api/v1/tasks', [...$base, 'title'=>'Parent'], $headers)->assertCreated()->json('data');
        $child = $this->postJson('/api/v1/tasks', [...$base, 'title'=>'Child', 'parent_id'=>$parent['id']], $headers)->assertCreated()->json('data');

        $this->putJson('/api/v1/tasks/'.$parent['id'], [...$base, 'title'=>'Parent', 'parent_id'=>$child['id']], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        $this->getJson('/api/v1/tasks/'.$parent['id'].'/details', $headers)
            ->assertOk()->assertJsonCount(1, 'data.subtasks');
        $this->assertGreaterThanOrEqual(1, Task::findOrFail($parent['id'])->activities()->count());
    }

    /** Handles the user and member operation for the current WorkIntel workflow. */ private function userAndMember(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();
        $member = $user->memberships()->with('workspace')->where('status', 'active')->firstOrFail();
        return [$user, $member];
    }

    /** Handles the headers operation for the current WorkIntel workflow. */ private function headers(int $workspaceId): array
    {
        return ['X-Workspace-Id' => (string) $workspaceId];
    }
}
