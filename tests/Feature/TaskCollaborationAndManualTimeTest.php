<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides task collaboration and manual time test behavior within the WorkIntel application. */ class TaskCollaborationAndManualTimeTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test owner can collaborate on tasks and lock a completed timesheet operation for the current WorkIntel workflow. */ public function test_owner_can_collaborate_on_tasks_and_lock_a_completed_timesheet(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $task = Task::query()->where('workspace_id', $membership->workspace_id)->whereNull('parent_id')->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $this->postJson('/api/v1/tasks/'.$task->id.'/comments', [
            'body' => 'Confirm acceptance criteria before release.',
        ], $headers)->assertCreated()->assertJsonPath('data.body', 'Confirm acceptance criteria before release.');

        $subtask = $this->postJson('/api/v1/tasks/'.$task->id.'/subtasks', [
            'title' => 'Run regression checklist',
            'status' => 'todo',
            'priority' => 'high',
            'assignee_ids' => [$membership->id],
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('tasks', [
            'id' => $subtask->json('data.id'),
            'parent_id' => $task->id,
            'project_id' => $task->project_id,
        ]);

        $this->postJson('/api/v1/tasks/'.$task->id.'/subtasks', [
            'title' => 'Invalid assignment',
            'assignee_ids' => [999999],
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('assignee_ids.0');

        $attachment = UploadedFile::fake()->create('brief.pdf', 120, 'application/pdf');
        $upload = $this->post('/api/v1/tasks/'.$task->id.'/attachments', [
            'file' => $attachment,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('task_attachments', [
            'id' => $upload->json('data.id'),
            'task_id' => $task->id,
            'original_name' => 'brief.pdf',
        ]);

        $start = now()->next('Monday')->setTime(9, 0, 0);
        $end = $start->copy()->addHours(2);
        $entry = $this->postJson('/api/v1/timesheets/entries', [
            'member_id' => $membership->id,
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'started_at' => $start->toIso8601String(),
            'ended_at' => $end->toIso8601String(),
            'billable' => true,
            'note' => 'Manual regression session',
        ], $headers)->assertCreated();

        $entryId = $entry->json('data.id');
        $this->assertSame('manual', TimeEntry::findOrFail($entryId)->source);

        $weekStart = $start->copy()->startOfWeek()->toDateString();
        $this->postJson('/api/v1/timesheets/submit', [
            'week_start' => $weekStart,
        ], $headers)->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->patchJson('/api/v1/timesheets/entries/'.$entryId.'/approval', [
            'status' => 'approved',
        ], $headers)->assertOk()->assertJsonPath('data.approval_status', 'approved');

        $weekResponse = $this->getJson('/api/v1/timesheets/week?start='.$weekStart, $headers)
            ->assertOk();

        $ownerRow = collect($weekResponse->json('rows'))
            ->firstWhere('member_id', $membership->id);
        $periodId = $ownerRow['period_id'] ?? null;

        $this->assertNotNull($periodId);
        $this->postJson('/api/v1/timesheets/periods/'.$periodId.'/lock', [], $headers)
            ->assertOk()->assertJsonPath('data.status', 'locked');

        $this->putJson('/api/v1/timesheets/entries/'.$entryId, [
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'started_at' => $start->toIso8601String(),
            'ended_at' => $end->copy()->addHour()->toIso8601String(),
            'billable' => true,
        ], $headers)->assertUnprocessable();
    }
}
