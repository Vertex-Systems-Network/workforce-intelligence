<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Workspace;
use App\Models\WorkspaceModule;
use App\Services\Modules\WorkspaceModuleService;
use App\Support\ModuleCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Provides p4 module doctor behavior within the WorkIntel application. */ class ModuleManagerDoctor extends Command
{
    protected $signature = 'workintel:p4-doctor {--json}';
    protected $description = 'Validate the P4 workspace module registry, dependencies, permissions and workspace state.';

    /** Executes the command, job, or request handler. */ public function handle(WorkspaceModuleService $modules): int
    {
        $checks = [];
        foreach (['workspace_modules', 'workspace_module_events'] as $table) {
            $checks[] = ['name' => $table.' table', 'ok' => Schema::hasTable($table), 'detail' => Schema::hasTable($table) ? 'present' : 'missing'];
        }
        foreach (['modules.view', 'modules.manage'] as $slug) {
            $exists = Schema::hasTable('permissions') && Permission::query()->where('slug', $slug)->exists();
            $checks[] = ['name' => 'permission '.$slug, 'ok' => $exists, 'detail' => $exists ? 'present' : 'missing'];
        }

        $catalogKeys = ModuleCatalog::keys();
        $checks[] = ['name' => 'module catalog', 'ok' => count($catalogKeys) >= 20, 'detail' => count($catalogKeys).' registered workspace modules'];

        $dependencyErrors = [];
        foreach ($catalogKeys as $key) {
            foreach (ModuleCatalog::dependencies($key) as $dependency) {
                if (! ModuleCatalog::definition($dependency)) $dependencyErrors[] = "{$key} -> {$dependency}";
            }
        }
        $checks[] = [
            'name' => 'dependency graph',
            'ok' => $dependencyErrors === [],
            'detail' => $dependencyErrors === [] ? 'all dependencies resolve to registered modules' : 'unknown dependencies: '.implode(', ', $dependencyErrors),
        ];

        if (Schema::hasTable('workspace_modules')) {
            $missingRows = 0;
            $dependencyViolations = [];
            Workspace::query()->orderBy('id')->chunkById(100, function ($workspaces) use ($modules, &$missingRows, &$dependencyViolations, $catalogKeys) {
                foreach ($workspaces as $workspace) {
                    $modules->initializeWorkspace($workspace);
                    $rows = WorkspaceModule::query()->where('workspace_id', $workspace->id)->get()->keyBy('module_key');
                    $missingRows += count(array_diff($catalogKeys, $rows->keys()->all()));
                    foreach ($catalogKeys as $key) {
                        $row = $rows->get($key);
                        if (! $row || ! $row->is_enabled) continue;
                        foreach (ModuleCatalog::dependencies($key) as $dependency) {
                            if (! (bool) optional($rows->get($dependency))->is_enabled) {
                                $dependencyViolations[] = "workspace {$workspace->id}: {$key} requires {$dependency}";
                            }
                        }
                    }
                }
            });
            $checks[] = ['name' => 'workspace module rows', 'ok' => $missingRows === 0, 'detail' => $missingRows === 0 ? 'all workspaces have a complete module registry' : "{$missingRows} module row(s) missing"];
            $checks[] = ['name' => 'enabled dependency consistency', 'ok' => $dependencyViolations === [], 'detail' => $dependencyViolations === [] ? 'enabled modules have enabled dependencies' : implode('; ', array_slice($dependencyViolations, 0, 10))];
        }

        $ok = collect($checks)->every('ok');
        if ($this->option('json')) {
            $this->line(json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($checks as $check) {
                $this->line(($check['ok'] ? '<info>OK</info>' : '<error>FAIL</error>').' '.$check['name'].' — '.$check['detail']);
            }
            $ok ? $this->info('P4 Module Manager doctor passed.') : $this->error('P4 Module Manager doctor found blocking issues.');
        }
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
