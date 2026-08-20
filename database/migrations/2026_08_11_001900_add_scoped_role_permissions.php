<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NEW_PERMISSIONS = [
        ['People', 'people.view_team'],
        ['People', 'people.view_all'],
        ['Projects', 'projects.view_assigned'],
        ['Projects', 'projects.view_all'],
        ['Tasks', 'tasks.view_own'],
        ['Tasks', 'tasks.view_team'],
        ['Tasks', 'tasks.view_all'],
        ['Tasks', 'tasks.manage_team'],
    ];

    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        foreach (self::NEW_PERMISSIONS as [$group, $slug]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
            );
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'slug');
        $hasPivotTimestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');

        $grant = function (int $roleId, array $slugs) use ($permissionIds, $hasPivotTimestamps): void {
            foreach ($slugs as $slug) {
                $permissionId = $permissionIds[$slug] ?? null;
                if (! $permissionId) continue;
                $row = ['role_id' => $roleId, 'permission_id' => $permissionId];
                if ($hasPivotTimestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                DB::table('role_permissions')->insertOrIgnore($row);
            }
        };

        $revoke = function (int $roleId, array $slugs) use ($permissionIds): void {
            $ids = collect($slugs)->map(fn ($slug) => $permissionIds[$slug] ?? null)->filter()->values();
            if ($ids->isNotEmpty()) {
                DB::table('role_permissions')->where('role_id', $roleId)->whereIn('permission_id', $ids)->delete();
            }
        };

        foreach (DB::table('roles')->where('is_system', true)->get(['id', 'slug']) as $role) {
            $roleId = (int) $role->id;
            switch ($role->slug) {
                case 'owner':
                case 'admin':
                    $grant($roleId, collect(self::NEW_PERMISSIONS)->pluck(1)->all());
                    break;
                case 'hr':
                    $revoke($roleId, ['people.view', 'integrations.view', 'security.audit.view']);
                    $grant($roleId, ['people.view_all']);
                    break;
                case 'manager':
                    $revoke($roleId, ['people.view', 'projects.view', 'tasks.view', 'clients.view', 'devices.view', 'integrations.view', 'security.audit.view']);
                    $grant($roleId, ['people.view_team', 'projects.view_all', 'tasks.view_all']);
                    break;
                case 'team-lead':
                    $revoke($roleId, ['people.view', 'projects.view', 'tasks.view', 'tasks.manage']);
                    $grant($roleId, ['people.view_team', 'projects.view_assigned', 'tasks.view_team', 'tasks.manage_team']);
                    break;
                case 'payroll-manager':
                    $revoke($roleId, ['people.view']);
                    $grant($roleId, ['people.view_all', 'attendance.view_own']);
                    break;
                case 'employee':
                    $revoke($roleId, ['organization.view', 'projects.view', 'tasks.view']);
                    $grant($roleId, ['projects.view_assigned', 'tasks.view_own']);
                    break;
            }
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        // Scoped permissions are intentionally retained on rollback. Removing
        // permission rows can invalidate historical role configurations.
    }
};
