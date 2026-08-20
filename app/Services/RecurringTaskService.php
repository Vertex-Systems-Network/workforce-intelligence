<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskRecurrence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Provides recurring task service behavior within the WorkIntel application. */ class RecurringTaskService
{
    /** Handles the generate due tasks operation for the current WorkIntel workflow. */ public function generateDueTasks(?int $workspaceId = null): int
    {
        $query = TaskRecurrence::query()
            ->with(['task.assignees', 'task.observers', 'task.tags'])
            ->where('active', true)
            ->where('next_run_at', '<=', now())
            ->when($workspaceId, fn ($builder) => $builder->where('workspace_id', $workspaceId));

        $generated = 0;

        foreach ($query->get() as $recurrence) {
            $workspace = \App\Models\Workspace::find($recurrence->workspace_id);
            if (! $workspace || ! app(\App\Services\Modules\WorkspaceModuleService::class)->shouldProcessBackground($workspace, 'tasks')) continue;
            $safety = 0;
            while ($recurrence->active && $recurrence->next_run_at->lte(now()) && $safety < 100) {
                if (! $this->generate($recurrence)) break;
                $generated++;
                $recurrence->refresh();
                $safety++;
            }
        }

        return $generated;
    }

    /** Handles the generate operation for the current WorkIntel workflow. */ public function generate(TaskRecurrence $recurrence): ?Task
    {
        $recurrence->loadMissing(['task.assignees', 'task.observers', 'task.tags']);
        $template = $recurrence->task;
        if (! $template || ! $recurrence->active) return null;

        if ($recurrence->ends_on && $recurrence->next_run_at->toDateString() > $recurrence->ends_on->toDateString()) {
            $recurrence->update(['active' => false]);
            return null;
        }

        return DB::transaction(function () use ($recurrence, $template) {
            $dueAt = $recurrence->next_run_at->copy();
            $existing = Task::query()
                ->where('recurrence_template_id', $template->id)
                ->where('due_at', $dueAt)
                ->first();

            $workflow = app(\App\Services\Tasks\TaskWorkflowService::class);
            $workspace = \App\Models\Workspace::findOrFail($template->workspace_id);
            $workflow->ensureDefaults($workspace);
            $status = $workflow->defaultStatus($template->workspace_id);
            $startAt = null;
            if ($template->start_at && $template->due_at) {
                $startAt = $dueAt->copy()->subSeconds(max(0, $template->start_at->diffInSeconds($template->due_at, false)));
            }

            $task = $existing ?: Task::create([
                'workspace_id' => $template->workspace_id,
                'project_id' => $template->project_id,
                'parent_id' => $template->parent_id,
                'recurrence_template_id' => $template->id,
                'task_status_id' => $status->id,
                'owner_member_id' => $template->owner_member_id,
                'title' => $template->title,
                'description' => $template->description,
                'description_html' => $template->description_html,
                'status' => $status->slug,
                'priority' => $template->priority,
                'estimated_minutes' => $template->estimated_minutes,
                'start_at' => $startAt,
                'due_at' => $dueAt,
                'position' => $workflow->nextPosition($template->workspace_id, $status->id),
                'billable' => $template->billable,
                'client_visible' => $template->client_visible,
                'created_by' => $template->created_by,
                'completed_at' => $status->is_completed ? now() : null,
            ]);

            if (! $existing) {
                $task->assignees()->sync($template->assignees->pluck('id')->all());
                $task->observers()->sync($template->observers->pluck('id')->all());
                $task->tags()->sync($template->tags->pluck('id')->all());
                app(\App\Services\Tasks\TaskActivityService::class)->log($task, null, 'created', ['source' => 'recurrence', 'template_task_id' => $template->id]);
            }

            $next = $this->nextRun($dueAt, $recurrence->frequency, $recurrence->interval);
            $active = ! $recurrence->ends_on || $next->toDateString() <= $recurrence->ends_on->toDateString();

            $recurrence->update([
                'last_generated_at' => now(),
                'next_run_at' => $next,
                'active' => $active,
            ]);

            return $task;
        });
    }

    /** Handles the next run operation for the current WorkIntel workflow. */ public function nextRun(Carbon $from, string $frequency, int $interval): Carbon
    {
        $interval = max(1, $interval);

        return match ($frequency) {
            'daily' => $from->copy()->addDays($interval),
            'weekly' => $from->copy()->addWeeks($interval),
            'monthly' => $from->copy()->addMonthsNoOverflow($interval),
            default => throw new \InvalidArgumentException('Unsupported recurrence frequency.'),
        };
    }
}
