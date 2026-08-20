<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\Access\WorkScopeService;
use App\Services\Tasks\TaskWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Provides task board controller behavior within the WorkIntel application. */ class TaskBoardController extends Controller
{
    /** Handles the move operation for the current WorkIntel workflow. */ public function move(Request $request, Task $task, TaskWorkflowService $workflow): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        abort_unless((int) $task->workspace_id === (int) $workspace->id, 404);
        abort_unless(app(WorkScopeService::class)->canManageTask($viewer, $task), 403, 'You can only move tasks in your allowed work scope.');

        $data = $request->validate([
            'status_id' => ['required', 'integer'],
            'previous_task_id' => ['nullable', 'integer'],
            'next_task_id' => ['nullable', 'integer'],
        ]);
        if (($data['previous_task_id'] ?? null) === $task->id || ($data['next_task_id'] ?? null) === $task->id) {
            throw ValidationException::withMessages(['position' => ['A task cannot be positioned relative to itself.']]);
        }

        $target = $workflow->resolveStatus($workspace->id, (int) $data['status_id']);
        foreach (['previous_task_id', 'next_task_id'] as $key) {
            if (empty($data[$key])) continue;
            $neighbor = Task::where('workspace_id', $workspace->id)->findOrFail((int) $data[$key]);
            abort_unless(app(WorkScopeService::class)->canViewTask($viewer, $neighbor), 403, 'A board neighbor is outside your allowed work scope.');
        }

        $moved = $workflow->move($task, $target, $viewer, $data['previous_task_id'] ?? null, $data['next_task_id'] ?? null);
        return response()->json(['data' => $moved->load(['workflowStatus', 'project:id,name,code', 'owner.user:id,first_name,last_name', 'assignees.user:id,first_name,last_name', 'observers.user:id,first_name,last_name', 'tags'])]);
    }
}
