<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions')) return;

        $permissions = DB::table('permissions')
            ->whereIn('slug', ['automations.view', 'automations.manage', 'automations.runs.view'])
            ->pluck('id', 'slug');
        if ($permissions->isEmpty()) return;

        // Automation run payloads can cross HR/payroll/project/security domains.
        // Keep system defaults least-privileged; administrators can explicitly
        // delegate automation permissions from Roles & Access when appropriate.
        $restrictedRoleIds = DB::table('roles')
            ->where('is_system', true)
            ->whereIn('slug', ['hr', 'manager', 'team-lead', 'payroll-manager', 'employee', 'client'])
            ->pluck('id');
        if ($restrictedRoleIds->isNotEmpty()) {
            DB::table('role_permissions')
                ->whereIn('role_id', $restrictedRoleIds)
                ->whereIn('permission_id', $permissions->values())
                ->delete();
        }

        $hasTimestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');
        foreach (DB::table('roles')->where('is_system', true)->whereIn('slug', ['owner', 'admin'])->get(['id']) as $role) {
            foreach ($permissions as $permissionId) {
                $row = ['role_id' => $role->id, 'permission_id' => $permissionId];
                if ($hasTimestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                DB::table('role_permissions')->insertOrIgnore($row);
            }
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        // Security-hardening migration is intentionally not reverted.
    }
};
