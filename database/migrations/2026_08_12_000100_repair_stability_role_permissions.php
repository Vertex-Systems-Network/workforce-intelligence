<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $critical = [
            ['Projects', 'projects.view_assigned'],
            ['Projects', 'projects.view_all'],
            ['Projects', 'projects.manage'],
            ['Tasks', 'tasks.view_own'],
            ['Tasks', 'tasks.view_team'],
            ['Tasks', 'tasks.view_all'],
            ['Tasks', 'tasks.manage_team'],
            ['Tasks', 'tasks.manage'],
            ['People', 'people.view_team'],
            ['People', 'people.view_all'],
            ['Screenshots', 'screenshots.settings_manage'],
        ];

        foreach ($critical as [$group, $slug]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
            );
        }

        $ids = DB::table('permissions')->pluck('id', 'slug');
        $hasTimestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');

        $grant = function (string $roleSlug, array $slugs) use ($ids, $hasTimestamps): void {
            foreach (DB::table('roles')->where('is_system', true)->where('slug', $roleSlug)->get(['id']) as $role) {
                foreach ($slugs as $slug) {
                    $permissionId = $ids[$slug] ?? null;
                    if (! $permissionId) continue;
                    $row = ['role_id' => $role->id, 'permission_id' => $permissionId];
                    if ($hasTimestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                    DB::table('role_permissions')->insertOrIgnore($row);
                }
            }
        };

        // Owner/Admin are fixed full-access roles in the application contract.
        foreach (DB::table('roles')->where('is_system', true)->whereIn('slug', ['owner', 'admin'])->get(['id']) as $role) {
            foreach (DB::table('permissions')->pluck('id') as $permissionId) {
                $row = ['role_id' => $role->id, 'permission_id' => $permissionId];
                if ($hasTimestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                DB::table('role_permissions')->insertOrIgnore($row);
            }
        }

        // Repair only minimum core grants for editable system roles; never revoke user customizations.
        $grant('manager', ['people.view_team', 'projects.view_all', 'projects.manage', 'tasks.view_all', 'tasks.manage']);
        $grant('team-lead', ['people.view_team', 'projects.view_assigned', 'tasks.view_team', 'tasks.manage_team']);
        $grant('employee', ['projects.view_assigned', 'tasks.view_own']);
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        // Intentionally non-destructive: removing grants on rollback can lock active users out.
    }
};
