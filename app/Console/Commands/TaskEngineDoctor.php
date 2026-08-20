<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Workspace;
use App\Services\Tasks\TaskWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Provides p5 task engine doctor behavior within the WorkIntel application. */ class TaskEngineDoctor extends Command
{
    protected $signature = 'workintel:p5-doctor {--json}';
    protected $description = 'Validate P5 Task Engine V2 schema, workflow defaults, task mappings and permissions.';

    /** Executes the command, job, or request handler. */ public function handle(TaskWorkflowService $workflow): int
    {
        $checks = [];
        $tables = ['task_statuses','task_status_transitions','task_tags','task_tag_assignments','task_observers','task_checklist_items','task_relations','task_activities'];
        foreach ($tables as $table) $checks[] = ['name' => $table.' table', 'ok' => Schema::hasTable($table), 'detail' => Schema::hasTable($table) ? 'present' : 'missing'];
        foreach (['task_status_id','owner_member_id','description_html','start_at','position'] as $column) {
            $ok = Schema::hasTable('tasks') && Schema::hasColumn('tasks', $column);
            $checks[] = ['name' => 'tasks.'.$column, 'ok' => $ok, 'detail' => $ok ? 'present' : 'missing'];
        }
        $permission = Schema::hasTable('permissions') && Permission::where('slug', 'tasks.workflow_manage')->exists();
        $checks[] = ['name' => 'tasks.workflow_manage permission', 'ok' => $permission, 'detail' => $permission ? 'present' : 'missing'];

        if (Schema::hasTable('task_statuses') && Schema::hasTable('workspaces')) {
            $missingDefaults = [];
            foreach (Workspace::query()->orderBy('id')->get() as $workspace) {
                $workflow->ensureDefaults($workspace);
                $active = TaskStatus::where('workspace_id', $workspace->id)->where('is_archived', false)->count();
                $defaults = TaskStatus::where('workspace_id', $workspace->id)->where('is_archived', false)->where('is_default', true)->count();
                if ($active < 1 || $defaults !== 1) $missingDefaults[] = "workspace {$workspace->id}: active={$active}, defaults={$defaults}";
            }
            $checks[] = ['name' => 'workspace workflow defaults', 'ok' => $missingDefaults === [], 'detail' => $missingDefaults === [] ? 'every workspace has one active default status' : implode('; ', $missingDefaults)];
        }

        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks','task_status_id') && Schema::hasTable('task_statuses')) {
            $unmapped = Task::query()->whereNull('task_status_id')->count();
            $orphaned = Task::query()->whereNotNull('task_status_id')->whereDoesntHave('workflowStatus')->count();
            $checks[] = ['name' => 'task status mapping', 'ok' => $unmapped === 0 && $orphaned === 0, 'detail' => "unmapped={$unmapped}; orphaned={$orphaned}"];
        }

        $ok = collect($checks)->every('ok');
        if ($this->option('json')) $this->line(json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT));
        else {
            foreach ($checks as $check) $this->line(($check['ok'] ? '[PASS] ' : '[FAIL] ').$check['name'].' — '.$check['detail']);
        }
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
