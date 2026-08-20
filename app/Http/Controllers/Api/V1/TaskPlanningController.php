<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\TaskRecurrence;
use App\Services\RecurringTaskService;
use App\Services\Access\WorkScopeService;
use App\Services\Tasks\TaskActivityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Provides task planning controller behavior within the WorkIntel application. */ class TaskPlanningController extends Controller
{
    /** Handles the store dependency operation for the current WorkIntel workflow. */ public function storeDependency(Request $request, Task $task, TaskActivityService $activity): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureTask($request, $task);
        $data = $request->validate([
            'depends_on_task_id' => ['required', 'integer'],
            'type' => ['sometimes', Rule::in(['finish_to_start', 'start_to_start', 'finish_to_finish', 'start_to_finish'])],
        ]);

        $dependsOn = Task::query()->where('workspace_id', $workspace->id)->findOrFail($data['depends_on_task_id']);
        abort_unless(app(WorkScopeService::class)->canViewTask($request->attributes->get('workspaceMember'), $dependsOn), 403, 'Dependency task is outside your allowed work scope.');
        if ($dependsOn->id === $task->id) {
            throw ValidationException::withMessages(['depends_on_task_id' => ['A task cannot depend on itself.']]);
        }
        if ($this->wouldCreateCycle($task->id, $dependsOn->id)) {
            throw ValidationException::withMessages(['depends_on_task_id' => ['This dependency would create a circular task chain.']]);
        }

        $dependency = TaskDependency::updateOrCreate(
            ['task_id' => $task->id, 'depends_on_task_id' => $dependsOn->id],
            ['workspace_id' => $workspace->id, 'type' => $data['type'] ?? 'finish_to_start']
        );

        $activity->log($task, $request->attributes->get('workspaceMember'), 'dependency_added', ['dependency_id' => $dependency->id, 'depends_on_task_id' => $dependsOn->id, 'type' => $dependency->type]);
        return response()->json(['data' => $dependency->load('dependsOnTask:id,project_id,title,status,due_at')], 201);
    }

    /** Handles the destroy dependency operation for the current WorkIntel workflow. */ public function destroyDependency(Request $request, Task $task, TaskDependency $dependency, TaskActivityService $activity): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureTask($request, $task);
        abort_unless($dependency->workspace_id === $workspace->id && $dependency->task_id === $task->id, 404);
        $activity->log($task, $request->attributes->get('workspaceMember'), 'dependency_removed', ['dependency_id' => $dependency->id, 'depends_on_task_id' => $dependency->depends_on_task_id]);
        $dependency->delete();
        return response()->json(['message' => 'Dependency removed.']);
    }

    /** Updates update recurrence data for the requested resource. */ public function updateRecurrence(Request $request, Task $task, RecurringTaskService $service, TaskActivityService $activity): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureTask($request, $task);
        $data = $request->validate([
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'interval' => ['required', 'integer', 'min:1', 'max:52'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $start = Carbon::parse($data['starts_on'])->startOfDay();
        if ($task->due_at && $task->due_at->toDateString() === $start->toDateString()) {
            $start->setTimeFrom($task->due_at);
        } else {
            $start->setTime(9, 0);
        }

        $recurrence = TaskRecurrence::updateOrCreate(
            ['task_id' => $task->id],
            [
                'workspace_id' => $workspace->id,
                'frequency' => $data['frequency'],
                'interval' => $data['interval'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'next_run_at' => $start,
                'active' => $data['active'] ?? true,
            ]
        );

        if ($recurrence->next_run_at->isPast() && $recurrence->active) {
            $service->generate($recurrence);
            $recurrence->refresh();
        }

        $activity->log($task, $request->attributes->get('workspaceMember'), 'recurrence_updated', ['frequency' => $recurrence->frequency, 'interval' => $recurrence->interval, 'next_run_at' => optional($recurrence->next_run_at)->toISOString()]);
        return response()->json(['data' => $recurrence]);
    }

    /** Handles the destroy recurrence operation for the current WorkIntel workflow. */ public function destroyRecurrence(Request $request, Task $task, TaskActivityService $activity): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureTask($request, $task);
        $task->recurrence()?->delete();
        $activity->log($task, $request->attributes->get('workspaceMember'), 'recurrence_removed');
        return response()->json(['message' => 'Recurring schedule removed.']);
    }

    /** Handles the would create cycle operation for the current WorkIntel workflow. */ private function wouldCreateCycle(int $taskId, int $dependsOnTaskId): bool
    {
        $frontier = [$dependsOnTaskId];
        $visited = [];

        while ($frontier) {
            $current = array_shift($frontier);
            if ($current === $taskId) return true;
            if (isset($visited[$current])) continue;
            $visited[$current] = true;

            $next = TaskDependency::query()->where('task_id', $current)->pluck('depends_on_task_id')->all();
            array_push($frontier, ...$next);
        }

        return false;
    }

    /** Handles the ensure task operation for the current WorkIntel workflow. */ private function ensureTask(Request $request, Task $task): void
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        abort_unless($task->workspace_id === $workspace->id, 404);
        abort_unless(app(WorkScopeService::class)->canManageTask($viewer, $task), 403, 'You can only plan tasks in your allowed team scope.');
    }
}
