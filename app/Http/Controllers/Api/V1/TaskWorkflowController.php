<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskStatusTransition;
use App\Models\TaskTag;
use App\Services\Tasks\TaskWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Provides task workflow controller behavior within the WorkIntel application. */ class TaskWorkflowController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request, TaskWorkflowService $workflow): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $workflow->ensureDefaults($workspace);

        $statuses = TaskStatus::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_archived', false)
            ->with(['outgoingTransitions:id,workspace_id,from_status_id,to_status_id,require_comment'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (TaskStatus $status) {
                $status->setAttribute('allowed_to_ids', $status->outgoingTransitions->pluck('to_status_id')->map(fn ($id) => (int) $id)->values());
                return $status;
            });

        $tags = TaskTag::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();

        return response()->json([
            'statuses' => $statuses,
            'tags' => $tags,
            'can_manage_workflow' => $request->attributes->get('workspaceMember')->hasPermission('tasks.workflow_manage'),
        ]);
    }

    /** Handles the store status operation for the current WorkIntel workflow. */ public function storeStatus(Request $request, TaskWorkflowService $workflow): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'group' => ['required', Rule::in(['backlog', 'todo', 'active', 'review', 'blocked', 'done', 'cancelled'])],
            'is_default' => ['sometimes', 'boolean'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);

        return DB::transaction(function () use ($workspace, $data, $workflow) {
            if (($data['is_default'] ?? false) === true) {
                TaskStatus::where('workspace_id', $workspace->id)->update(['is_default' => false]);
            }
            $status = TaskStatus::create([
                'workspace_id' => $workspace->id,
                'name' => trim($data['name']),
                'slug' => $workflow->uniqueSlug($workspace->id, $data['name']),
                'color' => strtolower($data['color']),
                'group' => $data['group'],
                'sort_order' => ((int) TaskStatus::where('workspace_id', $workspace->id)->max('sort_order')) + 1000,
                'is_default' => (bool) ($data['is_default'] ?? false),
                'is_completed' => (bool) ($data['is_completed'] ?? ($data['group'] === 'done')),
            ]);
            return response()->json(['data' => $status], 201);
        });
    }

    /** Updates update status data for the requested resource. */ public function updateStatus(Request $request, TaskStatus $status, TaskWorkflowService $workflow): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $status->workspace_id === (int) $workspace->id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'group' => ['sometimes', 'required', Rule::in(['backlog', 'todo', 'active', 'review', 'blocked', 'done', 'cancelled'])],
            'is_default' => ['sometimes', 'boolean'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);
        if ($status->is_default && array_key_exists('is_default', $data) && $data['is_default'] === false) {
            throw ValidationException::withMessages(['is_default' => ['Make another status the default instead of leaving the workflow without a default status.']]);
        }

        return DB::transaction(function () use ($workspace, $status, $data, $workflow) {
            if (($data['is_default'] ?? false) === true) {
                TaskStatus::where('workspace_id', $workspace->id)->where('id', '!=', $status->id)->update(['is_default' => false]);
            }
            if (isset($data['name']) && trim($data['name']) !== $status->name) {
                $data['slug'] = $workflow->uniqueSlug($workspace->id, $data['name'], $status->id);
                $data['name'] = trim($data['name']);
            }
            if (isset($data['color'])) $data['color'] = strtolower($data['color']);
            $status->update($data);

            // Keep legacy status slug in sync for all tasks using this workflow status.
            Task::where('workspace_id', $workspace->id)->where('task_status_id', $status->id)->update([
                'status' => $status->fresh()->slug,
                'completed_at' => $status->fresh()->is_completed ? DB::raw('COALESCE(completed_at, CURRENT_TIMESTAMP)') : null,
            ]);
            return response()->json(['data' => $status->fresh()]);
        });
    }

    /** Handles the reorder statuses operation for the current WorkIntel workflow. */ public function reorderStatuses(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate(['status_ids' => ['required', 'array', 'min:1'], 'status_ids.*' => ['integer']]);
        $ids = array_values(array_unique(array_map('intval', $data['status_ids'])));
        $valid = TaskStatus::where('workspace_id', $workspace->id)->where('is_archived', false)->whereIn('id', $ids)->count();
        if ($valid !== count($ids)) throw ValidationException::withMessages(['status_ids' => ['One or more statuses do not belong to this workspace.']]);
        foreach ($ids as $index => $id) TaskStatus::whereKey($id)->update(['sort_order' => ($index + 1) * 1000]);
        return response()->json(['message' => 'Task status order updated.']);
    }

    /** Updates update transitions data for the requested resource. */ public function updateTransitions(Request $request, TaskStatus $status): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $status->workspace_id === (int) $workspace->id, 404);
        $data = $request->validate(['to_status_ids' => ['required', 'array'], 'to_status_ids.*' => ['integer']]);
        $ids = array_values(array_unique(array_map('intval', $data['to_status_ids'])));
        $valid = TaskStatus::where('workspace_id', $workspace->id)->where('is_archived', false)->whereIn('id', $ids)->count();
        if ($valid !== count($ids)) throw ValidationException::withMessages(['to_status_ids' => ['One or more transition targets are invalid.']]);
        $ids = array_values(array_filter($ids, fn ($id) => $id !== (int) $status->id));

        DB::transaction(function () use ($workspace, $status, $ids) {
            TaskStatusTransition::where('workspace_id', $workspace->id)->where('from_status_id', $status->id)->delete();
            foreach ($ids as $id) TaskStatusTransition::create(['workspace_id' => $workspace->id, 'from_status_id' => $status->id, 'to_status_id' => $id]);
        });
        return response()->json(['message' => 'Allowed transitions updated.', 'to_status_ids' => $ids]);
    }

    /** Handles the destroy status operation for the current WorkIntel workflow. */ public function destroyStatus(Request $request, TaskStatus $status, TaskWorkflowService $workflow): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $status->workspace_id === (int) $workspace->id, 404);
        $data = $request->validate(['replacement_status_id' => ['nullable', 'integer']]);
        $used = Task::where('workspace_id', $workspace->id)->where('task_status_id', $status->id)->exists();

        if ($status->is_default) throw ValidationException::withMessages(['status' => ['The default status cannot be archived. Make another status default first.']]);
        if ($used && empty($data['replacement_status_id'])) {
            throw ValidationException::withMessages(['replacement_status_id' => ['Choose a replacement status before archiving a status that is in use.']]);
        }

        DB::transaction(function () use ($workspace, $status, $data, $used, $workflow) {
            if ($used) {
                $replacement = $workflow->resolveStatus($workspace->id, (int) $data['replacement_status_id']);
                if ($replacement->id === $status->id) throw ValidationException::withMessages(['replacement_status_id' => ['Replacement status must be different.']]);
                Task::where('workspace_id', $workspace->id)->where('task_status_id', $status->id)->chunkById(100, function ($tasks) use ($replacement, $workflow) {
                    foreach ($tasks as $task) $workflow->applyStatus($task, $replacement, null, false, 'status_archived');
                });
            }
            $status->update(['is_archived' => true]);
        });
        return response()->json(['message' => 'Task status archived.']);
    }

    /** Handles the store tag operation for the current WorkIntel workflow. */ public function storeTag(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate(['name' => ['required', 'string', 'max:60'], 'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        $base = substr(\Illuminate\Support\Str::slug($data['name'], '_') ?: 'tag', 0, 56);
        $slug = $base; $n = 2;
        while (TaskTag::where('workspace_id', $workspace->id)->where('slug', $slug)->exists()) $slug = substr($base, 0, 52).'_'.$n++;
        $tag = TaskTag::create(['workspace_id' => $workspace->id, 'name' => trim($data['name']), 'slug' => $slug, 'color' => strtolower($data['color'])]);
        return response()->json(['data' => $tag], 201);
    }

    /** Updates update tag data for the requested resource. */ public function updateTag(Request $request, TaskTag $tag): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $tag->workspace_id === (int) $workspace->id, 404);
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:60'], 'color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        if (isset($data['name'])) $data['name'] = trim($data['name']);
        if (isset($data['color'])) $data['color'] = strtolower($data['color']);
        $tag->update($data);
        return response()->json(['data' => $tag->fresh()]);
    }

    /** Handles the destroy tag operation for the current WorkIntel workflow. */ public function destroyTag(Request $request, TaskTag $tag): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $tag->workspace_id === (int) $workspace->id, 404);
        if ($tag->tasks()->exists()) $tag->update(['is_archived' => true]); else $tag->delete();
        return response()->json(['message' => 'Task tag removed from the active catalog.']);
    }
}
