<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\TaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTag;
use App\Models\WorkspaceMember;
use App\Services\Access\WorkScopeService;
use App\Services\Tasks\TaskActivityService;
use App\Services\Tasks\TaskContentService;
use App\Services\Tasks\TaskWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Provides task controller behavior within the WorkIntel application. */ class TaskController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request, TaskWorkflowService $workflow): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $workflow->ensureDefaults($workspace);

        $query = Task::query()
            ->with([
                'project:id,name,code',
                'workflowStatus:id,workspace_id,name,slug,color,group,sort_order,is_default,is_completed',
                'owner.user:id,first_name,last_name',
                'assignees.user:id,first_name,last_name',
                'observers.user:id,first_name,last_name',
                'tags:id,workspace_id,name,slug,color',
            ])
            ->withCount(['checklistItems as checklist_total', 'checklistItems as checklist_completed' => fn (Builder $q) => $q->where('is_completed', true)])
            ->where('workspace_id', $workspace->id);

        app(WorkScopeService::class)->scopeTasks($query, $viewer);

        if ($request->integer('project_id')) $query->where('project_id', $request->integer('project_id'));
        if ($request->integer('status_id')) $query->where('task_status_id', $request->integer('status_id'));
        if ($request->boolean('root_only')) $query->whereNull('parent_id');
        if ($request->boolean('my')) {
            $query->where(function (Builder $scope) use ($viewer) {
                $scope->where('owner_member_id', $viewer->id)
                    ->orWhereHas('assignees', fn (Builder $q) => $q->where('workspace_members.id', $viewer->id))
                    ->orWhereHas('observers', fn (Builder $q) => $q->where('workspace_members.id', $viewer->id));
            });
        }
        if (filled($request->query('q'))) {
            $needle = trim((string) $request->query('q'));
            $query->where(function (Builder $q) use ($needle) {
                $q->where('title', 'like', "%{$needle}%")
                    ->orWhere('description', 'like', "%{$needle}%")
                    ->orWhereHas('tags', fn (Builder $tags) => $tags->where('task_tags.name', 'like', "%{$needle}%"));
            });
        }

        $query->orderBy('position')->orderBy('due_at')->orderBy('id');

        return response()->json(['data' => $query->get()]);
    }

    /** Creates and persists the requested resource. */ public function store(
        TaskRequest $request,
        TaskWorkflowService $workflow,
        TaskContentService $content,
        TaskActivityService $activity,
    ): JsonResponse {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $workflow->ensureDefaults($workspace);
        $data = $request->validated();
        $project = $this->projectForWorkspace($workspace->id, (int) $data['project_id']);
        $this->assertTeamManageProject($viewer, $project);
        $this->validateMembers($workspace->id, $data, $viewer);
        $this->validateTags($workspace->id, $data['tag_ids'] ?? []);
        $parent = $this->validateParent($workspace->id, $project->id, $data['parent_id'] ?? null, null);
        $status = $workflow->resolveStatus($workspace->id, $data['status_id'] ?? null, $data['status'] ?? null);
        $html = $content->sanitize($data['description_html'] ?? null);
        $plain = $content->plainText($html, $data['description'] ?? null);

        $task = DB::transaction(function () use ($workspace, $request, $viewer, $data, $project, $parent, $status, $workflow, $activity, $html, $plain) {
            $task = Task::create([
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'parent_id' => $parent?->id,
                'task_status_id' => $status->id,
                'owner_member_id' => $data['owner_member_id'] ?? null,
                'title' => trim($data['title']),
                'description' => $plain,
                'description_html' => $html,
                'status' => $status->slug,
                'priority' => $data['priority'],
                'estimated_minutes' => $data['estimated_minutes'] ?? null,
                'start_at' => $data['start_at'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'position' => $workflow->nextPosition($workspace->id, $status->id),
                'billable' => $data['billable'],
                'client_visible' => $data['client_visible'] ?? false,
                'created_by' => $request->user()->id,
                'completed_at' => $status->is_completed ? now() : null,
            ]);

            $task->assignees()->sync($data['assignee_ids'] ?? []);
            $task->observers()->sync($data['observer_ids'] ?? []);
            $task->tags()->sync($data['tag_ids'] ?? []);
            $activity->log($task, $viewer, 'created', [
                'status_id' => $status->id,
                'project_id' => $project->id,
                'assignee_ids' => array_values($data['assignee_ids'] ?? []),
                'observer_ids' => array_values($data['observer_ids'] ?? []),
                'tag_ids' => array_values($data['tag_ids'] ?? []),
            ]);
            return $task;
        });

        return response()->json(['data' => $this->loadTask($task)], 201);
    }

    /** Updates update data for the requested resource. */ public function update(
        TaskRequest $request,
        Task $task,
        TaskWorkflowService $workflow,
        TaskContentService $content,
        TaskActivityService $activity,
    ): JsonResponse {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $this->ensureWorkspaceTask($workspace->id, $task);
        abort_unless(app(WorkScopeService::class)->canManageTask($viewer, $task), 403, 'You can only manage tasks in your allowed team scope.');
        $workflow->ensureDefaults($workspace);
        $data = $request->validated();
        $project = $this->projectForWorkspace($workspace->id, (int) $data['project_id']);
        $this->assertTeamManageProject($viewer, $project);
        $this->validateMembers($workspace->id, $data, $viewer);
        $this->validateTags($workspace->id, $data['tag_ids'] ?? []);
        $parent = $this->validateParent($workspace->id, $project->id, $data['parent_id'] ?? null, $task);
        $statusId = array_key_exists('status_id', $data) ? ($data['status_id'] ?: null) : (array_key_exists('status', $data) ? null : $task->task_status_id);
        $statusSlug = array_key_exists('status', $data) && ! $statusId ? $data['status'] : null;
        $targetStatus = $workflow->resolveStatus($workspace->id, $statusId, $statusSlug);
        $html = $content->sanitize($data['description_html'] ?? $task->description_html);
        $plain = $content->plainText($html, $data['description'] ?? $task->description);

        DB::transaction(function () use ($task, $viewer, $data, $project, $parent, $targetStatus, $workflow, $activity, $html, $plain) {
            $before = [
                'project_id' => $task->project_id,
                'parent_id' => $task->parent_id,
                'owner_member_id' => $task->owner_member_id,
                'title' => $task->title,
                'priority' => $task->priority,
                'estimated_minutes' => $task->estimated_minutes,
                'start_at' => optional($task->start_at)->toISOString(),
                'due_at' => optional($task->due_at)->toISOString(),
                'billable' => $task->billable,
                'client_visible' => $task->client_visible,
            ];
            $beforeAssignees = $task->assignees()->pluck('workspace_members.id')->map(fn ($id) => (int) $id)->all();
            $beforeObservers = $task->observers()->pluck('workspace_members.id')->map(fn ($id) => (int) $id)->all();
            $beforeTags = $task->tags()->pluck('task_tags.id')->map(fn ($id) => (int) $id)->all();

            $task->update([
                'project_id' => $project->id,
                'parent_id' => $parent?->id,
                'owner_member_id' => $data['owner_member_id'] ?? null,
                'title' => trim($data['title']),
                'description' => $plain,
                'description_html' => $html,
                'priority' => $data['priority'],
                'estimated_minutes' => $data['estimated_minutes'] ?? null,
                'start_at' => $data['start_at'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'billable' => $data['billable'],
                'client_visible' => $data['client_visible'] ?? false,
            ]);
            $workflow->applyStatus($task, $targetStatus, $viewer, true, 'task_form');
            if (array_key_exists('assignee_ids', $data)) $task->assignees()->sync($data['assignee_ids'] ?? []);
            if (array_key_exists('observer_ids', $data)) $task->observers()->sync($data['observer_ids'] ?? []);
            if (array_key_exists('tag_ids', $data)) $task->tags()->sync($data['tag_ids'] ?? []);

            $after = [
                'project_id' => $task->project_id,
                'parent_id' => $task->parent_id,
                'owner_member_id' => $task->owner_member_id,
                'title' => $task->title,
                'priority' => $task->priority,
                'estimated_minutes' => $task->estimated_minutes,
                'start_at' => optional($task->start_at)->toISOString(),
                'due_at' => optional($task->due_at)->toISOString(),
                'billable' => $task->billable,
                'client_visible' => $task->client_visible,
            ];
            $activity->log($task, $viewer, 'updated', ['before' => $before, 'after' => $after]);

            if (array_key_exists('assignee_ids', $data)) {
                $afterAssignees = array_values(array_map('intval', $data['assignee_ids'] ?? [])); sort($beforeAssignees); sort($afterAssignees);
                if ($beforeAssignees !== $afterAssignees) $activity->log($task, $viewer, 'assignees_changed', ['before' => $beforeAssignees, 'after' => $afterAssignees]);
            }
            if (array_key_exists('observer_ids', $data)) {
                $afterObservers = array_values(array_map('intval', $data['observer_ids'] ?? [])); sort($beforeObservers); sort($afterObservers);
                if ($beforeObservers !== $afterObservers) $activity->log($task, $viewer, 'observers_changed', ['before' => $beforeObservers, 'after' => $afterObservers]);
            }
            if (array_key_exists('tag_ids', $data)) {
                $afterTags = array_values(array_map('intval', $data['tag_ids'] ?? [])); sort($beforeTags); sort($afterTags);
                if ($beforeTags !== $afterTags) $activity->log($task, $viewer, 'tags_changed', ['before' => $beforeTags, 'after' => $afterTags]);
            }
        });

        return response()->json(['data' => $this->loadTask($task->fresh())]);
    }

    /** Removes destroy data from the requested resource. */ public function destroy(Request $request, Task $task, TaskActivityService $activity): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $this->ensureWorkspaceTask($workspace->id, $task);
        abort_unless(app(WorkScopeService::class)->canManageTask($viewer, $task), 403, 'You can only manage tasks in your allowed team scope.');
        abort_if($task->timeEntries()->exists(), 422, 'A task with tracked time cannot be moved to Trash. Move it to a completed status instead.');
        abort_if($task->subtasks()->exists(), 422, 'A task with subtasks cannot be moved to Trash. Move or trash the subtasks first.');
        $activity->log($task, $viewer, 'deleted', ['title' => $task->title]);
        $task->delete();
        return response()->json(['message' => 'Task moved to Trash.']);
    }

    /** Handles the project for workspace operation for the current WorkIntel workflow. */ private function projectForWorkspace(int $workspaceId, int $projectId): Project
    {
        return Project::query()->where('workspace_id', $workspaceId)->findOrFail($projectId);
    }

    /** Validates validate members input before it is processed. */ private function validateMembers(int $workspaceId, array $data, WorkspaceMember $viewer): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', [
            ...($data['assignee_ids'] ?? []),
            ...($data['observer_ids'] ?? []),
            $data['owner_member_id'] ?? null,
        ]))));
        if (! $ids) return;

        $validCount = WorkspaceMember::query()->where('workspace_id', $workspaceId)->where('status', 'active')->whereIn('id', $ids)->count();
        if ($validCount !== count($ids)) throw ValidationException::withMessages(['assignee_ids' => ['One or more selected members are not active in this workspace.']]);

        if ($viewer->hasPermission('tasks.manage_team') && ! $viewer->hasPermission('tasks.manage')) {
            $allowed = app(WorkScopeService::class)->teamMemberIds($viewer, 'tasks');
            if (collect($ids)->contains(fn ($id) => ! in_array((int) $id, $allowed, true))) {
                throw ValidationException::withMessages(['assignee_ids' => ['Team-scoped managers can only assign/observe tasks with members in their allowed team scope.']]);
            }
        }
    }

    /** Validates validate tags input before it is processed. */ private function validateTags(int $workspaceId, array $tagIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $tagIds)));
        if (! $ids) return;
        $count = TaskTag::query()->where('workspace_id', $workspaceId)->where('is_archived', false)->whereIn('id', $ids)->count();
        if ($count !== count($ids)) throw ValidationException::withMessages(['tag_ids' => ['One or more task tags are not available in this workspace.']]);
    }

    /** Validates validate parent input before it is processed. */ private function validateParent(int $workspaceId, int $projectId, mixed $parentId, ?Task $task): ?Task
    {
        if (! $parentId) return null;
        $parent = Task::query()->where('workspace_id', $workspaceId)->where('project_id', $projectId)->findOrFail((int) $parentId);
        if ($task && $parent->id === $task->id) throw ValidationException::withMessages(['parent_id' => ['A task cannot be its own parent.']]);

        if ($task) {
            $cursor = $parent;
            $visited = [];
            while ($cursor) {
                if ($cursor->id === $task->id) throw ValidationException::withMessages(['parent_id' => ['This parent would create a circular task hierarchy.']]);
                if (isset($visited[$cursor->id])) break;
                $visited[$cursor->id] = true;
                $cursor = $cursor->parent_id ? Task::where('workspace_id', $workspaceId)->find($cursor->parent_id) : null;
            }
        }
        return $parent;
    }

    /** Handles the assert team manage project operation for the current WorkIntel workflow. */ private function assertTeamManageProject(WorkspaceMember $viewer, Project $project): void
    {
        if (! $viewer->hasPermission('tasks.manage_team') || $viewer->hasPermission('tasks.manage')) return;
        abort_unless(app(WorkScopeService::class)->canViewProject($viewer, $project), 403, 'Team-scoped managers can only create or move tasks inside projects assigned to their work scope.');
    }

    /** Handles the ensure workspace task operation for the current WorkIntel workflow. */ private function ensureWorkspaceTask(int $workspaceId, Task $task): void
    {
        abort_unless((int) $task->workspace_id === $workspaceId, 404);
    }

    /** Handles the load task operation for the current WorkIntel workflow. */ private function loadTask(Task $task): Task
    {
        return $task->load([
            'project:id,name,code',
            'workflowStatus:id,workspace_id,name,slug,color,group,sort_order,is_default,is_completed',
            'owner.user:id,first_name,last_name',
            'assignees.user:id,first_name,last_name',
            'observers.user:id,first_name,last_name',
            'tags:id,workspace_id,name,slug,color',
        ])->loadCount(['checklistItems as checklist_total', 'checklistItems as checklist_completed' => fn (Builder $q) => $q->where('is_completed', true)]);
    }
}
