<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskStatusTransition;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides task workflow service behavior within the WorkIntel application. */ class TaskWorkflowService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly TaskActivityService $activity) {}

    public const DEFAULTS = [
        ['name' => 'Todo', 'slug' => 'todo', 'color' => '#64748b', 'group' => 'todo', 'sort_order' => 1000, 'is_default' => true, 'is_completed' => false],
        ['name' => 'In Progress', 'slug' => 'in_progress', 'color' => '#2563eb', 'group' => 'active', 'sort_order' => 2000, 'is_default' => false, 'is_completed' => false],
        ['name' => 'Review', 'slug' => 'review', 'color' => '#d97706', 'group' => 'review', 'sort_order' => 3000, 'is_default' => false, 'is_completed' => false],
        ['name' => 'Blocked', 'slug' => 'blocked', 'color' => '#dc2626', 'group' => 'blocked', 'sort_order' => 4000, 'is_default' => false, 'is_completed' => false],
        ['name' => 'Done', 'slug' => 'done', 'color' => '#16a34a', 'group' => 'done', 'sort_order' => 5000, 'is_default' => false, 'is_completed' => true],
    ];

    /** Handles the ensure defaults operation for the current WorkIntel workflow. */ public function ensureDefaults(Workspace $workspace): void
    {
        if (! Schema::hasTable('task_statuses')) return;

        foreach (self::DEFAULTS as $definition) {
            TaskStatus::firstOrCreate(
                ['workspace_id' => $workspace->id, 'slug' => $definition['slug']],
                $definition
            );
        }

        $default = TaskStatus::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_default', true)
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->first();

        if (! $default) {
            TaskStatus::query()
                ->where('workspace_id', $workspace->id)
                ->where('is_archived', false)
                ->orderBy('sort_order')
                ->first()?->update(['is_default' => true]);
        }

        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'task_status_id')) {
            $statuses = TaskStatus::where('workspace_id', $workspace->id)->pluck('id', 'slug');
            foreach ($statuses as $slug => $id) {
                Task::query()->where('workspace_id', $workspace->id)->whereNull('task_status_id')->where('status', $slug)->update(['task_status_id' => $id]);
            }
            if (($fallback = $statuses['todo'] ?? null)) {
                Task::query()->where('workspace_id', $workspace->id)->whereNull('task_status_id')->update(['task_status_id' => $fallback]);
            }
        }
    }

    /** Handles the default status operation for the current WorkIntel workflow. */ public function defaultStatus(int $workspaceId): TaskStatus
    {
        return TaskStatus::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_archived', false)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->firstOrFail();
    }

    /** Returns resolve status data required by the current workflow. */ public function resolveStatus(int $workspaceId, ?int $statusId = null, ?string $slug = null): TaskStatus
    {
        $query = TaskStatus::query()->where('workspace_id', $workspaceId)->where('is_archived', false);
        if ($statusId) return (clone $query)->whereKey($statusId)->firstOrFail();
        if ($slug) return (clone $query)->where('slug', $slug)->firstOrFail();
        return $this->defaultStatus($workspaceId);
    }

    /** Determines whether the can transition condition is satisfied. */ public function canTransition(Task $task, TaskStatus $target): bool
    {
        if (! $task->task_status_id || (int) $task->task_status_id === (int) $target->id) return true;
        $outgoing = TaskStatusTransition::query()
            ->where('workspace_id', $task->workspace_id)
            ->where('from_status_id', $task->task_status_id);

        // No explicit rows means unrestricted outgoing transitions. This keeps
        // newly-created custom statuses usable until an owner configures rules.
        if (! $outgoing->exists()) return true;

        return (clone $outgoing)->where('to_status_id', $target->id)->exists();
    }

    /** Handles the apply status operation for the current WorkIntel workflow. */ public function applyStatus(Task $task, TaskStatus $target, ?WorkspaceMember $actor = null, bool $enforceTransition = true, string $source = 'task_update'): void
    {
        if ((int) $target->workspace_id !== (int) $task->workspace_id || $target->is_archived) {
            throw ValidationException::withMessages(['status_id' => ['The selected status is not available in this workspace.']]);
        }

        $fromId = $task->task_status_id;
        $fromSlug = $task->status;
        if ($enforceTransition && ! $this->canTransition($task, $target)) {
            throw ValidationException::withMessages(['status_id' => ["Transition from '{$fromSlug}' to '{$target->name}' is not allowed."]]);
        }

        $task->task_status_id = $target->id;
        $task->status = $target->slug;
        $task->completed_at = $target->is_completed ? ($task->completed_at ?? now()) : null;
        $task->save();

        if ((int) $fromId !== (int) $target->id) {
            $this->activity->log($task, $actor, 'status_changed', [
                'from_status_id' => $fromId,
                'from_status' => $fromSlug,
                'to_status_id' => $target->id,
                'to_status' => $target->slug,
                'source' => $source,
            ]);
        }
    }

    /** Handles the next position operation for the current WorkIntel workflow. */ public function nextPosition(int $workspaceId, int $statusId): int
    {
        return ((int) Task::query()->where('workspace_id', $workspaceId)->where('task_status_id', $statusId)->max('position')) + 1000;
    }

    /** Handles the move operation for the current WorkIntel workflow. */ public function move(Task $task, TaskStatus $target, ?WorkspaceMember $actor, ?int $previousTaskId, ?int $nextTaskId): Task
    {
        return DB::transaction(function () use ($task, $target, $actor, $previousTaskId, $nextTaskId) {
            $task = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $this->applyStatus($task, $target, $actor, true, 'board_drag');

            $previous = $previousTaskId ? Task::query()
                ->where('workspace_id', $task->workspace_id)
                ->where('task_status_id', $target->id)
                ->whereKey($previousTaskId)
                ->lockForUpdate()
                ->first() : null;
            $next = $nextTaskId ? Task::query()
                ->where('workspace_id', $task->workspace_id)
                ->where('task_status_id', $target->id)
                ->whereKey($nextTaskId)
                ->lockForUpdate()
                ->first() : null;

            if ($previous && $next && $next->position - $previous->position <= 1) {
                $this->rebalance($task->workspace_id, $target->id);
                $previous->refresh();
                $next->refresh();
            }
            if (! $previous && $next && $next->position <= 1) {
                $this->rebalance($task->workspace_id, $target->id);
                $next->refresh();
            }

            $position = match (true) {
                $previous && $next => (int) floor(($previous->position + $next->position) / 2),
                (bool) $previous => $previous->position + 1000,
                (bool) $next => max(1, $next->position - 1000),
                default => $this->nextPosition($task->workspace_id, $target->id),
            };

            $task->update(['position' => $position]);
            $this->activity->log($task, $actor, 'reordered', [
                'status_id' => $target->id,
                'previous_task_id' => $previousTaskId,
                'next_task_id' => $nextTaskId,
                'position' => $position,
            ]);
            return $task->fresh();
        });
    }

    /** Handles the rebalance operation for the current WorkIntel workflow. */ public function rebalance(int $workspaceId, int $statusId): void
    {
        Task::query()
            ->where('workspace_id', $workspaceId)
            ->where('task_status_id', $statusId)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id'])
            ->values()
            ->each(fn (Task $task, int $index) => Task::whereKey($task->id)->update(['position' => ($index + 1) * 1000]));
    }

    /** Handles the unique slug operation for the current WorkIntel workflow. */ public function uniqueSlug(int $workspaceId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name, '_') ?: 'status';
        $base = substr($base, 0, 56);
        $candidate = $base;
        $counter = 2;
        while (TaskStatus::query()->where('workspace_id', $workspaceId)->where('slug', $candidate)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $candidate = substr($base, 0, 52).'_'.$counter++;
        }
        return $candidate;
    }
}
