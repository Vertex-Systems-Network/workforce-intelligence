<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskChecklistItem;
use App\Models\TaskRelation;
use App\Services\Access\WorkScopeService;
use App\Services\Tasks\TaskActivityService;
use App\Services\Tasks\TaskWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Provides task collaboration controller behavior within the WorkIntel application. */ class TaskCollaborationController extends Controller
{
    /** Returns details for the requested resource. */ public function show(Request $request, Task $task): JsonResponse
    {
        $this->ensureTaskVisible($request, $task);

        $task->load([
            'project:id,name,code',
            'workflowStatus:id,workspace_id,name,slug,color,group,sort_order,is_default,is_completed',
            'owner.user:id,first_name,last_name',
            'parent:id,workspace_id,project_id,parent_id,task_status_id,title,status,priority,due_at',
            'parent.workflowStatus:id,name,slug,color,group,is_completed',
            'assignees.user:id,first_name,last_name',
            'observers.user:id,first_name,last_name',
            'tags:id,workspace_id,name,slug,color',
            'subtasks.workflowStatus:id,name,slug,color,group,is_completed',
            'subtasks.assignees.user:id,first_name,last_name',
            'comments.member.user:id,first_name,last_name',
            'attachments.member.user:id,first_name,last_name',
            'checklistItems.assignee.user:id,first_name,last_name',
            'checklistItems.completedBy.user:id,first_name,last_name',
            'dependencies.dependsOnTask:id,workspace_id,project_id,task_status_id,title,status,due_at',
            'dependencies.dependsOnTask.workflowStatus:id,name,slug,color,group,is_completed',
            'dependents.task:id,workspace_id,project_id,task_status_id,title,status,due_at',
            'dependents.task.workflowStatus:id,name,slug,color,group,is_completed',
            'relations.targetTask:id,workspace_id,project_id,task_status_id,title,status,due_at',
            'relations.targetTask.workflowStatus:id,name,slug,color,group,is_completed',
            'inverseRelations.sourceTask:id,workspace_id,project_id,task_status_id,title,status,due_at',
            'inverseRelations.sourceTask.workflowStatus:id,name,slug,color,group,is_completed',
            'activities.actor.user:id,first_name,last_name',
            'recurrence',
            'recurrenceInstances:id,workspace_id,project_id,recurrence_template_id,task_status_id,title,status,due_at',
            'recurrenceInstances.workflowStatus:id,name,slug,color,group,is_completed',
        ]);

        $viewer = $request->attributes->get('workspaceMember');
        $scope = app(WorkScopeService::class);
        if (! $viewer->hasPermission('tasks.manage') && ! $viewer->hasPermission('tasks.view_all') && ! $viewer->hasPermission('tasks.view')) {
            $task->setRelation('subtasks', $task->subtasks->filter(fn (Task $row) => $scope->canViewTask($viewer, $row))->values());
            $task->setRelation('dependencies', $task->dependencies->filter(fn ($row) => $row->dependsOnTask && $scope->canViewTask($viewer, $row->dependsOnTask))->values());
            $task->setRelation('dependents', $task->dependents->filter(fn ($row) => $row->task && $scope->canViewTask($viewer, $row->task))->values());
            $task->setRelation('relations', $task->relations->filter(fn ($row) => $row->targetTask && $scope->canViewTask($viewer, $row->targetTask))->values());
            $task->setRelation('inverseRelations', $task->inverseRelations->filter(fn ($row) => $row->sourceTask && $scope->canViewTask($viewer, $row->sourceTask))->values());
            $task->setRelation('recurrenceInstances', $task->recurrenceInstances->filter(fn (Task $row) => $scope->canViewTask($viewer, $row))->values());
        }

        return response()->json(['data' => $task]);
    }

    /** Handles the store comment operation for the current WorkIntel workflow. */ public function storeComment(Request $request, Task $task, TaskActivityService $activity): JsonResponse
    {
        $this->ensureTaskVisible($request, $task);
        $member = $request->attributes->get('workspaceMember');
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $comment = $task->comments()->create(['member_id' => $member->id, 'body' => trim($data['body'])]);
        $activity->log($task, $member, 'comment_added', ['comment_id' => $comment->id]);
        return response()->json(['data' => $comment->load('member.user:id,first_name,last_name')], 201);
    }

    /** Handles the store subtask operation for the current WorkIntel workflow. */ public function storeSubtask(Request $request, Task $task, TaskWorkflowService $workflow, TaskActivityService $activity): JsonResponse
    {
        $this->ensureTaskManageable($request, $task);
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'status_id' => ['nullable', 'integer'],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'owner_member_id' => ['nullable', 'integer'],
            'assignee_ids' => ['sometimes', 'array'],
            'assignee_ids.*' => ['integer'],
        ]);

        $memberIds = array_values(array_unique(array_filter(array_map('intval', [...($data['assignee_ids'] ?? []), $data['owner_member_id'] ?? null]))));
        $this->validateTaskMembers($workspace->id, $memberIds, $viewer);
        $status = $workflow->resolveStatus($workspace->id, $data['status_id'] ?? null);

        $subtask = Task::create([
            'workspace_id' => $workspace->id,
            'project_id' => $task->project_id,
            'parent_id' => $task->id,
            'task_status_id' => $status->id,
            'owner_member_id' => $data['owner_member_id'] ?? null,
            'title' => trim($data['title']),
            'status' => $status->slug,
            'priority' => $data['priority'] ?? $task->priority,
            'estimated_minutes' => $data['estimated_minutes'] ?? null,
            'start_at' => $data['start_at'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'position' => $workflow->nextPosition($workspace->id, $status->id),
            'billable' => $task->billable,
            'client_visible' => $task->client_visible,
            'created_by' => $request->user()->id,
            'completed_at' => $status->is_completed ? now() : null,
        ]);
        $subtask->assignees()->sync($data['assignee_ids'] ?? []);
        $activity->log($subtask, $viewer, 'created', ['parent_id' => $task->id, 'source' => 'subtask']);
        $activity->log($task, $viewer, 'subtask_added', ['subtask_id' => $subtask->id, 'title' => $subtask->title]);

        return response()->json(['data' => $subtask->load(['workflowStatus', 'assignees.user:id,first_name,last_name'])], 201);
    }

    /** Handles the store checklist item operation for the current WorkIntel workflow. */ public function storeChecklistItem(Request $request, Task $task, TaskActivityService $activity): JsonResponse
    {
        $this->ensureTaskManageable($request, $task);
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:300'],
            'assignee_member_id' => ['nullable', 'integer'],
            'due_at' => ['nullable', 'date'],
        ]);
        if ($data['assignee_member_id'] ?? null) $this->validateTaskMembers($workspace->id, [(int) $data['assignee_member_id']], $viewer);
        $item = $task->checklistItems()->create([
            'workspace_id' => $workspace->id,
            'title' => trim($data['title']),
            'sort_order' => ((int) $task->checklistItems()->max('sort_order')) + 1000,
            'assignee_member_id' => $data['assignee_member_id'] ?? null,
            'due_at' => $data['due_at'] ?? null,
        ]);
        $activity->log($task, $viewer, 'checklist_item_added', ['item_id' => $item->id, 'title' => $item->title]);
        return response()->json(['data' => $item->load('assignee.user:id,first_name,last_name')], 201);
    }

    /** Updates update checklist item data for the requested resource. */ public function updateChecklistItem(Request $request, Task $task, TaskChecklistItem $item, TaskActivityService $activity): JsonResponse
    {
        $this->ensureTaskVisible($request, $task);
        abort_unless((int) $item->task_id === (int) $task->id && (int) $item->workspace_id === (int) $task->workspace_id, 404);
        $viewer = $request->attributes->get('workspaceMember');
        $canManage = app(WorkScopeService::class)->canManageTask($viewer, $task);
        $rules = $canManage ? [
            'title' => ['sometimes', 'required', 'string', 'max:300'],
            'is_completed' => ['sometimes', 'boolean'],
            'assignee_member_id' => ['nullable', 'integer'],
            'due_at' => ['nullable', 'date'],
        ] : ['is_completed' => ['required', 'boolean']];
        $data = $request->validate($rules);
        if ($canManage && ($data['assignee_member_id'] ?? null)) $this->validateTaskMembers($task->workspace_id, [(int) $data['assignee_member_id']], $viewer);

        if (array_key_exists('title', $data)) $item->title = trim($data['title']);
        if ($canManage && array_key_exists('assignee_member_id', $data)) $item->assignee_member_id = $data['assignee_member_id'];
        if ($canManage && array_key_exists('due_at', $data)) $item->due_at = $data['due_at'];
        if (array_key_exists('is_completed', $data)) {
            $item->is_completed = (bool) $data['is_completed'];
            $item->completed_at = $item->is_completed ? now() : null;
            $item->completed_by_member_id = $item->is_completed ? $viewer->id : null;
        }
        $item->save();
        $activity->log($task, $viewer, $item->is_completed ? 'checklist_item_completed' : 'checklist_item_updated', ['item_id' => $item->id, 'title' => $item->title]);
        return response()->json(['data' => $item->fresh()->load(['assignee.user:id,first_name,last_name', 'completedBy.user:id,first_name,last_name'])]);
    }

    /** Handles the destroy checklist item operation for the current WorkIntel workflow. */ public function destroyChecklistItem(Request $request, Task $task, TaskChecklistItem $item, TaskActivityService $activity): JsonResponse
    {
        $this->ensureTaskManageable($request, $task);
        abort_unless((int) $item->task_id === (int) $task->id, 404);
        $viewer = $request->attributes->get('workspaceMember');
        $activity->log($task, $viewer, 'checklist_item_removed', ['item_id' => $item->id, 'title' => $item->title]);
        $item->delete();
        return response()->json(['message' => 'Checklist item removed.']);
    }

    /** Handles the store relation operation for the current WorkIntel workflow. */ public function storeRelation(Request $request, Task $task, TaskActivityService $activity): JsonResponse
    {
        $this->ensureTaskManageable($request, $task);
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $data = $request->validate(['target_task_id' => ['required', 'integer'], 'type' => ['sometimes', Rule::in(['related', 'duplicate'])]]);
        $target = Task::query()->where('workspace_id', $workspace->id)->findOrFail((int) $data['target_task_id']);
        abort_unless(app(WorkScopeService::class)->canViewTask($viewer, $target), 403, 'Related task is outside your allowed work scope.');
        if ($target->id === $task->id) throw ValidationException::withMessages(['target_task_id' => ['A task cannot relate to itself.']]);
        $relation = TaskRelation::firstOrCreate([
            'workspace_id' => $workspace->id,
            'source_task_id' => $task->id,
            'target_task_id' => $target->id,
            'type' => $data['type'] ?? 'related',
        ]);
        $activity->log($task, $viewer, 'relation_added', ['relation_id' => $relation->id, 'target_task_id' => $target->id, 'type' => $relation->type]);
        return response()->json(['data' => $relation->load('targetTask.workflowStatus')], 201);
    }

    /** Handles the destroy relation operation for the current WorkIntel workflow. */ public function destroyRelation(Request $request, Task $task, TaskRelation $relation, TaskActivityService $activity): JsonResponse
    {
        $this->ensureTaskManageable($request, $task);
        abort_unless((int) $relation->workspace_id === (int) $task->workspace_id && (int) $relation->source_task_id === (int) $task->id, 404);
        $viewer = $request->attributes->get('workspaceMember');
        $activity->log($task, $viewer, 'relation_removed', ['relation_id' => $relation->id, 'target_task_id' => $relation->target_task_id, 'type' => $relation->type]);
        $relation->delete();
        return response()->json(['message' => 'Task relation removed.']);
    }

    /** Handles the store attachment operation for the current WorkIntel workflow. */ public function storeAttachment(Request $request, Task $task, TaskActivityService $activity): JsonResponse
    {
        $this->ensureTaskManageable($request, $task);
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        $request->validate(['file' => ['required', 'file', 'max:10240']]);
        $file = $request->file('file');
        $path = $file->store("workspaces/{$workspace->id}/tasks/{$task->id}", 'local');
        $attachment = $task->attachments()->create([
            'member_id' => $member->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
        $activity->log($task, $member, 'attachment_added', ['attachment_id' => $attachment->id, 'name' => $attachment->original_name]);
        return response()->json(['data' => $attachment->load('member.user:id,first_name,last_name')], 201);
    }

    /** Handles the download attachment operation for the current WorkIntel workflow. */ public function downloadAttachment(Request $request, Task $task, TaskAttachment $attachment): StreamedResponse
    {
        $this->ensureTaskVisible($request, $task);
        abort_unless((int) $attachment->task_id === (int) $task->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    /** Handles the destroy attachment operation for the current WorkIntel workflow. */ public function destroyAttachment(Request $request, Task $task, TaskAttachment $attachment, TaskActivityService $activity): JsonResponse
    {
        $this->ensureTaskManageable($request, $task);
        abort_unless((int) $attachment->task_id === (int) $task->id, 404);
        $viewer = $request->attributes->get('workspaceMember');
        Storage::disk($attachment->disk)->delete($attachment->path);
        $activity->log($task, $viewer, 'attachment_removed', ['attachment_id' => $attachment->id, 'name' => $attachment->original_name]);
        $attachment->delete();
        return response()->json(['message' => 'Attachment deleted.']);
    }

    /** Validates validate task members input before it is processed. */ private function validateTaskMembers(int $workspaceId, array $ids, $viewer): void
    {
        if (! $ids) return;
        $normalized = array_values(array_unique(array_map('intval', $ids)));
        $validIds = \App\Models\WorkspaceMember::query()->where('workspace_id', $workspaceId)->where('status', 'active')->whereIn('id', $normalized)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $invalidIndex = collect($ids)->search(fn ($id) => ! in_array((int) $id, $validIds, true));
        if ($invalidIndex !== false) throw ValidationException::withMessages(['assignee_ids.'.$invalidIndex => ['The selected member is invalid or inactive.']]);
        if ($viewer->hasPermission('tasks.manage_team') && ! $viewer->hasPermission('tasks.manage')) {
            $allowed = app(WorkScopeService::class)->teamMemberIds($viewer, 'tasks');
            if (collect($ids)->contains(fn ($id) => ! in_array((int) $id, $allowed, true))) abort(403, 'Selected member is outside your team scope.');
        }
    }

    /** Handles the ensure task visible operation for the current WorkIntel workflow. */ private function ensureTaskVisible(Request $request, Task $task): void
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        abort_unless((int) $task->workspace_id === (int) $workspace->id, 404);
        abort_unless(app(WorkScopeService::class)->canViewTask($viewer, $task), 403, 'This task is outside your allowed work scope.');
    }

    /** Handles the ensure task manageable operation for the current WorkIntel workflow. */ private function ensureTaskManageable(Request $request, Task $task): void
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        abort_unless((int) $task->workspace_id === (int) $workspace->id, 404);
        abort_unless(app(WorkScopeService::class)->canManageTask($viewer, $task), 403, 'You can only manage tasks in your allowed team scope.');
    }
}
