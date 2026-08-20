<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides milestone four workflow test behavior within the WorkIntel application. */ class WorkspaceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test owner can manage planning finance holidays breaks and leave policies operation for the current WorkIntel workflow. */ public function test_owner_can_manage_planning_finance_holidays_breaks_and_leave_policies(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $tasks = Task::query()->where('workspace_id', $membership->workspace_id)->whereNull('parent_id')->take(2)->get();
        $first = $tasks->firstOrFail();
        $second = $tasks->last();

        $dependency = $this->postJson('/api/v1/tasks/'.$first->id.'/dependencies', [
            'depends_on_task_id' => $second->id,
            'type' => 'finish_to_start',
        ], $headers)->assertCreated();
        $this->assertDatabaseHas('task_dependencies', ['id' => $dependency->json('data.id'), 'task_id' => $first->id]);

        $this->postJson('/api/v1/tasks/'.$second->id.'/dependencies', [
            'depends_on_task_id' => $first->id,
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('depends_on_task_id');

        $this->putJson('/api/v1/tasks/'.$first->id.'/recurrence', [
            'frequency' => 'weekly',
            'interval' => 1,
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addMonths(3)->toDateString(),
            'active' => true,
        ], $headers)->assertOk()->assertJsonPath('data.frequency', 'weekly');

        $this->postJson('/api/v1/attendance/clock-in', [], $headers)->assertOk();
        $break = $this->postJson('/api/v1/attendance/breaks/start', [
            'type' => 'lunch',
            'paid' => false,
        ], $headers)->assertCreated();
        $this->postJson('/api/v1/attendance/breaks/'.$break->json('data.id').'/end', [], $headers)->assertOk();

        $holidayDate = now()->addDays(20)->toDateString();
        $this->postJson('/api/v1/holidays', [
            'name' => 'Product Release Day',
            'date' => $holidayDate,
            'type' => 'company',
            'paid' => true,
            'status' => 'active',
        ], $headers)->assertCreated();
        $this->assertDatabaseHas('holidays', ['workspace_id' => $membership->workspace_id, 'date' => $holidayDate]);

        $leaveType = $this->postJson('/api/v1/leave/types', [
            'name' => 'Learning Leave',
            'code' => 'LEARN',
            'is_paid' => true,
            'annual_allowance_days' => 6,
            'policy' => [
                'accrual_method' => 'monthly',
                'monthly_accrual_days' => 0.5,
                'carryover_days' => 2,
                'min_notice_days' => 2,
                'max_consecutive_days' => 3,
                'probation_months' => 0,
                'allow_negative_balance' => false,
                'requires_approval' => true,
                'exclude_weekends' => true,
                'exclude_holidays' => true,
            ],
        ], $headers)->assertCreated();
        $this->assertDatabaseHas('leave_policies', ['leave_type_id' => $leaveType->json('data.id'), 'accrual_method' => 'monthly']);

        $project = $first->project;
        $financials = $this->getJson('/api/v1/projects/'.$project->id.'/financials', $headers)->assertOk();
        $this->assertGreaterThanOrEqual(0, (float) $financials->json('data.labor_cost'));
        $this->postJson('/api/v1/projects/'.$project->id.'/expenses', [
            'name' => 'QA service',
            'category' => 'service',
            'amount' => 125.50,
            'currency' => 'USD',
            'incurred_on' => now()->toDateString(),
        ], $headers)->assertCreated();
        $this->assertDatabaseHas('project_expenses', ['project_id' => $project->id, 'name' => 'QA service']);
    }

    /** Handles the test timesheet approval is added to workflow history operation for the current WorkIntel workflow. */ public function test_timesheet_approval_is_added_to_workflow_history(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $entry = TimeEntry::query()->where('workspace_id', $membership->workspace_id)->firstOrFail();
        $this->patchJson('/api/v1/timesheets/entries/'.$entry->id.'/approval', [
            'status' => 'approved',
            'note' => 'Reviewed against project work.',
        ], $headers)->assertOk();

        $weekStart = Carbon::parse($entry->date)->startOfWeek(Carbon::MONDAY)->toDateString();
        $response = $this->getJson('/api/v1/timesheets/week?start='.$weekStart, $headers)->assertOk();
        $history = collect($response->json('history'));
        $this->assertTrue($history->contains(fn (array $action) => $action['action'] === 'approved' && (int) $action['time_entry_id'] === $entry->id));
    }
}
