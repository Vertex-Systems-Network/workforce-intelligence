<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Models\WorkspaceAccessSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Provides p0 stability doctor behavior within the WorkIntel application. */ class FoundationStabilityDoctor extends Command
{
    protected $signature = 'workintel:p0-doctor {--json}';
    protected $description = 'Validate P0 stability contracts: enterprise sessions, screenshot policy schema and core role access.';

    /** Executes the command, job, or request handler. */ public function handle(): int
    {
        $checks = [
            ['name' => 'workspace_access_sessions table', 'ok' => Schema::hasTable('workspace_access_sessions')],
            ['name' => 'WorkspaceAccessSession.user relation', 'ok' => method_exists(WorkspaceAccessSession::class, 'user')],
            ['name' => 'WorkspaceAccessSession.member relation', 'ok' => method_exists(WorkspaceAccessSession::class, 'member')],
            ['name' => 'screenshot_settings.interval_minutes', 'ok' => Schema::hasTable('screenshot_settings') && Schema::hasColumn('screenshot_settings', 'interval_minutes')],
        ];

        foreach (['projects.view_assigned', 'tasks.view_own', 'tasks.view_team', 'tasks.manage_team', 'tasks.view_all', 'tasks.manage', 'screenshots.settings_manage'] as $slug) {
            $checks[] = ['name' => 'permission '.$slug, 'ok' => Schema::hasTable('permissions') && Permission::query()->where('slug', $slug)->exists()];
        }

        if (Schema::hasTable('roles') && Schema::hasTable('role_permissions')) {
            foreach ([
                'employee' => ['projects.view_assigned', 'tasks.view_own'],
                'team-lead' => ['projects.view_assigned', 'tasks.view_team', 'tasks.manage_team'],
                'manager' => ['projects.view_all', 'projects.manage', 'tasks.view_all', 'tasks.manage'],
            ] as $roleSlug => $permissions) {
                $role = Role::query()->with('permissions:id,slug')->where('is_system', true)->where('slug', $roleSlug)->first();
                foreach ($permissions as $permission) {
                    $checks[] = [
                        'name' => $roleSlug.' has '.$permission,
                        'ok' => (bool) ($role && $role->permissions->contains('slug', $permission)),
                    ];
                }
            }
        }

        $ok = collect($checks)->every('ok');
        if ($this->option('json')) $this->line(json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT));
        else foreach ($checks as $check) $this->line(($check['ok'] ? '<info>OK</info>' : '<error>MISSING</error>').' '.$check['name']);

        $ok ? $this->info('P0 stability doctor passed.') : $this->error('P0 stability doctor found blocking issues.');
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
