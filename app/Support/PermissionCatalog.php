<?php

namespace App\Support;

use App\Models\Permission;
use Illuminate\Support\Collection;

/** Provides permission catalog behavior within the WorkIntel application. */ final class PermissionCatalog
{
    /**
     * Core permissions that exist in every workspace installation.
     *
     * Keeping this list in application code means a newly registered workspace
     * receives a complete Owner role even when demo data has not been seeded.
     */
    public const ITEMS = [
        ['People', 'people.view'], ['People', 'people.view_team'], ['People', 'people.view_all'], ['People', 'people.manage'],
        ['Organization', 'organization.view'], ['Organization', 'organization.manage'],
        ['Projects', 'projects.view'], ['Projects', 'projects.view_assigned'], ['Projects', 'projects.view_all'], ['Projects', 'projects.manage'],
        ['Tasks', 'tasks.view'], ['Tasks', 'tasks.view_own'], ['Tasks', 'tasks.view_team'], ['Tasks', 'tasks.view_all'], ['Tasks', 'tasks.manage_team'], ['Tasks', 'tasks.manage'], ['Tasks', 'tasks.workflow_manage'],
        ['Time', 'time.view_own'], ['Time', 'time.view_team'], ['Time', 'time.view_all'], ['Time', 'time.manage'],
        ['Attendance', 'attendance.view_own'], ['Attendance', 'attendance.view_team'], ['Attendance', 'attendance.manage'], ['Attendance', 'attendance.policy_manage'],
        ['Scheduling', 'scheduling.view_own'], ['Scheduling', 'scheduling.view_team'], ['Scheduling', 'scheduling.manage'],
        ['Approvals', 'approvals.view_own'], ['Approvals', 'approvals.review'], ['Approvals', 'approvals.workflow_manage'], ['Approvals', 'approvals.audit'],
        ['Activity', 'activity.view_own'], ['Activity', 'activity.view_team'], ['Activity', 'activity.view_all'], ['Activity', 'activity.manage'], ['Activity', 'activity.rules_manage'],
        ['Screenshots', 'screenshots.view_own'], ['Screenshots', 'screenshots.view_team'], ['Screenshots', 'screenshots.view_all'], ['Screenshots', 'screenshots.manage'], ['Screenshots', 'screenshots.settings_manage'], ['Screenshots', 'screenshots.storage_manage'],
        ['Payroll', 'payroll.view_own'], ['Payroll', 'payroll.view_all'], ['Payroll', 'payroll.manage'],
        ['Reports', 'reports.view'], ['Reports', 'reports.manage'],
        ['Documents', 'documents.view'], ['Documents', 'documents.generate'], ['Documents', 'documents.manage'], ['Documents', 'documents.templates_manage'], ['Documents', 'documents.share'], ['Documents', 'documents.sign'], ['Documents', 'documents.approve'], ['Documents', 'documents.components_manage'],
        ['Website', 'website.view'], ['Website', 'website.manage'], ['Website', 'website.publish'], ['Website', 'website.forms_manage'], ['Website', 'website.submissions_view'],
        ['Media', 'media.view'], ['Media', 'media.manage'], ['Lifecycle', 'trash.view'], ['Lifecycle', 'trash.restore'], ['Lifecycle', 'trash.purge'],
        ['Chat', 'chat.view'], ['Chat', 'chat.create'], ['Chat', 'chat.manage'], ['Chat', 'chat.moderate'], ['Chat', 'chat.guests_manage'], ['Chat', 'chat.retention_manage'], ['Chat', 'chat.export'], ['Chat', 'chat.legal_hold_manage'], ['Chat', 'chat.dlp_manage'],
        ['Clients', 'clients.view'], ['Clients', 'clients.manage'], ['Clients', 'client_payments.manage'], ['Clients', 'client_invoices.recurring_manage'],
        ['Devices', 'devices.view'], ['Devices', 'devices.manage'],
        ['Settings', 'settings.view'], ['Settings', 'settings.manage'], ['Access', 'access.view'], ['Access', 'access.manage'], ['Modules', 'modules.view'], ['Modules', 'modules.manage'], ['Billing', 'billing.manage'],
        ['Notifications', 'notifications.manage'],
        ['Integrations', 'integrations.view'], ['Integrations', 'integrations.manage'],
        ['API', 'api.manage'],
        ['Security', 'security.audit.view'], ['Security', 'security.manage'],
        ['HRIS', 'hris.view_own'], ['HRIS', 'hris.view_team'], ['HRIS', 'hris.view_all'], ['HRIS', 'hris.manage'], ['HRIS', 'hris.documents.manage'], ['HRIS', 'hris.assets.manage'], ['HRIS', 'hris.policies.manage'], ['HRIS', 'hris.lifecycle.manage'],
        ['Performance', 'performance.view_own'], ['Performance', 'performance.view_team'], ['Performance', 'performance.view_all'], ['Performance', 'performance.manage'], ['Performance', 'performance.reviews.manage'], ['Performance', 'performance.skills.manage'], ['Performance', 'performance.learning.manage'], ['Performance', 'performance.surveys.manage'], ['Performance', 'performance.compensation.manage'],
        ['Expenses', 'expenses.view_own'], ['Expenses', 'expenses.view_team'], ['Expenses', 'expenses.manage'], ['Expenses', 'expenses.policies.manage'],
        ['Procurement', 'procurement.view'], ['Procurement', 'procurement.request'], ['Procurement', 'procurement.manage'],
        ['Job Costing', 'job_costing.view'], ['Job Costing', 'job_costing.manage'], ['Finance', 'cost_centers.manage'],
        ['Payroll Compliance', 'payroll.compliance.view'], ['Payroll Compliance', 'payroll.compliance.manage'], ['Payroll Compliance', 'payroll.exports.manage'], ['Payroll Compliance', 'payroll.contractors.manage'],
        ['Field Workforce', 'field.view_own'], ['Field Workforce', 'field.view_team'], ['Field Workforce', 'field.manage'], ['Field Workforce', 'field.forms.manage'], ['Field Workforce', 'field.incidents.manage'],
        ['Enterprise', 'enterprise.identity.manage'], ['Enterprise', 'enterprise.scim.manage'], ['Enterprise', 'enterprise.security.manage'], ['Enterprise', 'enterprise.governance.manage'],
        ['Automations', 'automations.view'], ['Automations', 'automations.manage'], ['Automations', 'automations.runs.view'],
        ['Intelligence', 'intelligence.view_own'], ['Intelligence', 'intelligence.view_team'], ['Intelligence', 'intelligence.view_all'], ['Intelligence', 'intelligence.manage'], ['Intelligence', 'intelligence.rules_manage'],
        ['Platform', 'platform.view'], ['Platform', 'platform.manage'], ['Platform', 'platform.branding.manage'], ['Platform', 'platform.partner.manage'], ['Platform', 'platform.addons.manage'], ['Platform', 'platform.imports.manage'], ['Platform', 'platform.sandboxes.manage'],
    ];


    /** Handles the module key for group operation for the current WorkIntel workflow. */ public static function moduleKeyForGroup(string $group): string
    {
        return match ($group) {
            'Job Costing' => 'job-costing',
            'Payroll Compliance' => 'payroll-compliance',
            'Field Workforce' => 'field',
            default => strtolower(str_replace([' ', '_'], '-', trim($group))),
        };
    }

    /** @return array<int, array{key:string,label:string}> */
    /** Handles the modules operation for the current WorkIntel workflow. */ public static function modules(): array
    {
        $modules = [];
        foreach (self::ITEMS as [$group]) {
            $key = self::moduleKeyForGroup($group);
            $modules[$key] = ['key' => $key, 'label' => $group];
        }
        return array_values($modules);
    }

    /**
     * Create any missing permissions and return all permission IDs.
     *
     * @return Collection<int, int>
     */
    /** Synchronizes sync data with the current application state. */ public static function sync(): Collection
    {
        foreach (self::ITEMS as [$group, $slug]) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => ucwords(str_replace(['.', '_'], ' ', $slug)),
                    'group' => $group,
                ]
            );
        }

        return Permission::query()->orderBy('id')->pluck('id');
    }
}
