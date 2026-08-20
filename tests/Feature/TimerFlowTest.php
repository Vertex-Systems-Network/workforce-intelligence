<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides timer flow test behavior within the WorkIntel application. */ class TimerFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test task only timer derives its project operation for the current WorkIntel workflow. */ public function test_task_only_timer_derives_its_project(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'owner@acme.test')->firstOrFail();
        $member = $user->memberships()->firstOrFail();
        $task = Task::where('workspace_id', $member->workspace_id)->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/timer/start', [
            'task_id' => $task->id,
            'billable' => true,
        ], [
            'X-Workspace-Id' => (string) $member->workspace_id,
        ])->assertCreated()
          ->assertJsonPath('timer.task_id', $task->id)
          ->assertJsonPath('timer.project_id', $task->project_id);
    }

    /** Handles the test timer rejects a task from another project operation for the current WorkIntel workflow. */ public function test_timer_rejects_a_task_from_another_project(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'employee@acme.test')->firstOrFail();
        $member = $user->memberships()->firstOrFail();
        $projects = Project::where('workspace_id', $member->workspace_id)->take(2)->get();
        $this->assertCount(2, $projects);

        $task = Task::where('workspace_id', $member->workspace_id)
            ->where('project_id', $projects[0]->id)
            ->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/timer/start', [
            'project_id' => $projects[1]->id,
            'task_id' => $task->id,
        ], [
            'X-Workspace-Id' => (string) $member->workspace_id,
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('task_id');
    }
}
