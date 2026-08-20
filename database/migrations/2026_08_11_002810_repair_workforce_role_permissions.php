<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('permissions')) return;

        $permissions = [
            'HRIS' => ['hris.view_own','hris.view_team','hris.view_all','hris.manage','hris.documents.manage','hris.assets.manage','hris.policies.manage','hris.lifecycle.manage'],
            'Performance' => ['performance.view_own','performance.view_team','performance.view_all','performance.manage','performance.reviews.manage','performance.skills.manage','performance.learning.manage','performance.surveys.manage','performance.compensation.manage'],
            'Expenses' => ['expenses.view_own','expenses.view_team','expenses.manage','expenses.policies.manage'],
            'Procurement' => ['procurement.view','procurement.request','procurement.manage'],
            'Job Costing' => ['job_costing.view','job_costing.manage'],
            'Finance' => ['cost_centers.manage'],
            'Payroll Compliance' => ['payroll.compliance.view','payroll.compliance.manage','payroll.exports.manage','payroll.contractors.manage'],
            'Field Workforce' => ['field.view_own','field.view_team','field.manage','field.forms.manage','field.incidents.manage'],
            'Enterprise' => ['enterprise.identity.manage','enterprise.scim.manage','enterprise.security.manage','enterprise.governance.manage'],
        ];

        foreach ($permissions as $group => $slugs) {
            foreach ($slugs as $slug) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
                );
            }
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) return;

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

        $all = collect($permissions)->flatten()->values()->all();
        foreach (['owner', 'admin'] as $role) $grant($role, $all);
        $grant('hr', [
            'hris.view_own','hris.view_team','hris.view_all','hris.manage','hris.documents.manage','hris.assets.manage','hris.policies.manage','hris.lifecycle.manage',
            'performance.view_own','performance.view_team','performance.view_all','performance.manage','performance.reviews.manage','performance.skills.manage','performance.learning.manage','performance.surveys.manage','performance.compensation.manage',
            'expenses.view_own','expenses.view_team','field.view_own','field.view_team','field.incidents.manage',
        ]);
        $grant('manager', [
            'hris.view_own','hris.view_team','performance.view_own','performance.view_team','performance.manage','performance.reviews.manage',
            'expenses.view_own','expenses.view_team','procurement.view','procurement.request','procurement.manage','job_costing.view',
            'field.view_own','field.view_team','field.manage','field.incidents.manage',
        ]);
        $grant('team-lead', [
            'hris.view_own','hris.view_team','performance.view_own','performance.view_team','expenses.view_own','expenses.view_team',
            'procurement.view','procurement.request','field.view_own','field.view_team','field.incidents.manage',
        ]);
        $grant('payroll-manager', [
            'performance.view_own','performance.view_all','performance.compensation.manage','expenses.view_own','expenses.manage','job_costing.view',
            'payroll.compliance.view','payroll.compliance.manage','payroll.exports.manage','payroll.contractors.manage',
        ]);
        $grant('employee', ['hris.view_own','performance.view_own','expenses.view_own','procurement.request','field.view_own']);
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        // Permission repair is intentionally non-destructive. Removing role
        // permissions on rollback can unexpectedly lock users out of data.
    }
};
