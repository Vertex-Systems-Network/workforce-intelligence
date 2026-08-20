<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Schema;

/** Provides task activity service behavior within the WorkIntel application. */ class TaskActivityService
{
    /** Handles the log operation for the current WorkIntel workflow. */ public function log(Task $task, ?WorkspaceMember $actor, string $action, array $metadata = []): void
    {
        if (! Schema::hasTable('task_activities')) return;

        TaskActivity::create([
            'workspace_id' => $task->workspace_id,
            'task_id' => $task->id,
            'actor_member_id' => $actor?->id,
            'action' => $action,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
