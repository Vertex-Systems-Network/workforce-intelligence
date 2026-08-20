<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Provides module catalog behavior within the WorkIntel application. */ final class ModuleCatalog
{
    /**
     * Workspace-switchable product modules. Core kernel surfaces such as
     * authentication, settings, access control, billing and downloads are
     * intentionally not switchable so an owner cannot lock a workspace out.
     *
     * @var array<string,array<string,mixed>>
     */
    public const DEFINITIONS = [
        'people' => [
            'label' => 'People', 'category' => 'Core Work', 'description' => 'Employees, departments and workforce directory.',
            'dependencies' => [], 'default_enabled' => true, 'entitlement' => null, 'page' => 'people',
        ],
        'organization' => [
            'label' => 'Organization', 'category' => 'Core Work', 'description' => 'Teams, departments and reporting structure.',
            'dependencies' => ['people'], 'default_enabled' => true, 'entitlement' => null, 'page' => 'organization',
        ],
        'projects' => [
            'label' => 'Projects', 'category' => 'Core Work', 'description' => 'Project portfolio, members and project financial context.',
            'dependencies' => [], 'default_enabled' => true, 'entitlement' => null, 'page' => 'projects',
        ],
        'tasks' => [
            'label' => 'Tasks', 'category' => 'Core Work', 'description' => 'Task execution, collaboration, dependencies and recurrence.',
            'dependencies' => ['projects'], 'default_enabled' => true, 'entitlement' => null, 'page' => 'tasks',
        ],
        'time' => [
            'label' => 'Timesheets & Timer', 'category' => 'Workforce', 'description' => 'Tracked time, timesheets and timer workflows.',
            'dependencies' => ['projects'], 'default_enabled' => true, 'entitlement' => null, 'page' => 'time',
        ],
        'attendance' => [
            'label' => 'Attendance & Leave', 'category' => 'Workforce', 'description' => 'Clock events, attendance policy, leave and holidays.',
            'dependencies' => ['people'], 'default_enabled' => true, 'entitlement' => null, 'page' => 'attendance',
        ],
        'scheduling' => [
            'label' => 'Scheduling', 'category' => 'Workforce', 'description' => 'Shifts, roster, availability and workforce scheduling.',
            'dependencies' => ['attendance'], 'default_enabled' => true, 'entitlement' => null, 'page' => 'schedule',
        ],
        'approvals' => [
            'label' => 'Approvals', 'category' => 'Workflow', 'description' => 'Unified approval inbox, workflow definitions and delegations.',
            'dependencies' => ['people'], 'default_enabled' => true, 'entitlement' => null, 'page' => 'approvals',
        ],
        'activity' => [
            'label' => 'Activity Tracking', 'category' => 'Tracking', 'description' => 'Desktop and browser application/website activity tracking.',
            'dependencies' => ['people'], 'default_enabled' => true, 'entitlement' => 'feature.activity_tracking', 'page' => 'activity',
        ],
        'screenshots' => [
            'label' => 'Screenshots', 'category' => 'Tracking', 'description' => 'Screenshot capture, retention and review.',
            'dependencies' => ['activity'], 'default_enabled' => true, 'entitlement' => 'feature.screenshots', 'page' => 'screenshots',
        ],
        'devices' => [
            'label' => 'Devices & Agents', 'category' => 'Tracking', 'description' => 'Desktop agents, browser enrollments and remote commands.',
            'dependencies' => ['people'], 'default_enabled' => true, 'entitlement' => 'feature.desktop_agent', 'page' => 'devices',
        ],
        'clients' => [
            'label' => 'Clients', 'category' => 'Business', 'description' => 'Client records, client portal, invoices and client reports.',
            'dependencies' => ['projects'], 'default_enabled' => true, 'entitlement' => null, 'page' => 'clients',
        ],
        'hris' => [
            'label' => 'HRIS', 'category' => 'People & HR', 'description' => 'Employee records, documents, contracts, assets and lifecycle.',
            'dependencies' => ['people'], 'default_enabled' => true, 'entitlement' => null, 'page' => 'hris',
        ],
        'performance' => [
            'label' => 'Performance & Growth', 'category' => 'People & HR', 'description' => 'Goals, reviews, skills, learning and development.',
            'dependencies' => ['people'], 'default_enabled' => true, 'entitlement' => null, 'page' => 'performance',
        ],
        'payroll' => [
            'label' => 'Payroll', 'category' => 'Finance', 'description' => 'Compensation, payroll runs, adjustments and employee pay.',
            'dependencies' => ['people'], 'default_enabled' => true, 'entitlement' => 'feature.payroll', 'page' => 'payroll',
        ],
        'payroll-compliance' => [
            'label' => 'Payroll Compliance', 'category' => 'Finance', 'description' => 'Compliance packs, benefits, contractors and payroll exports.',
            'dependencies' => ['payroll'], 'default_enabled' => true, 'entitlement' => 'feature.payroll', 'page' => 'payroll-compliance',
        ],
        'finance' => [
            'label' => 'Expenses & Job Costing', 'category' => 'Finance', 'description' => 'Expenses, procurement, cost centers and job costing.',
            'dependencies' => ['people', 'projects'], 'default_enabled' => true, 'entitlement' => null, 'page' => 'finance',
        ],
        'reports' => [
            'label' => 'Reports', 'category' => 'Intelligence', 'description' => 'Saved reports, exports and scheduled reporting.',
            'dependencies' => [], 'default_enabled' => true, 'entitlement' => 'feature.advanced_reports', 'page' => 'reports',
        ],
        'documents' => [
            'label' => 'Document Studio', 'category' => 'Business', 'description' => 'Reusable PDF templates, versioning, previews and generated document history.',
            'dependencies' => [], 'default_enabled' => true, 'entitlement' => null, 'page' => 'documents',
        ],
        'website' => [
            'label' => 'Website Studio', 'category' => 'Business', 'description' => 'Public pages, reusable sections, lead forms, multilingual publishing and custom-domain delivery.',
            'dependencies' => [], 'default_enabled' => true, 'entitlement' => 'feature.website_builder', 'page' => 'website',
        ],
        'chat' => [
            'label' => 'Chat & Collaboration', 'category' => 'Collaboration', 'description' => 'Direct messages, channels, project/task threads, mentions, reactions and realtime presence.',
            'dependencies' => ['people'], 'default_enabled' => true, 'entitlement' => null, 'page' => 'chat',
        ],
        'intelligence' => [
            'label' => 'Workforce Intelligence', 'category' => 'Intelligence', 'description' => 'Explainable workforce, project, payroll and staffing signals.',
            'dependencies' => [], 'default_enabled' => true, 'entitlement' => 'feature.workforce_intelligence', 'page' => 'insights',
        ],
        'field' => [
            'label' => 'Field Workforce', 'category' => 'Operations', 'description' => 'Mobile work orders, checkpoints, forms and incidents.',
            'dependencies' => ['people'], 'default_enabled' => true, 'entitlement' => null, 'page' => 'field',
        ],
        'automations' => [
            'label' => 'Automation Studio', 'category' => 'Workflow', 'description' => 'Trigger-condition-action workflows, integrations and run history.',
            'dependencies' => [], 'default_enabled' => true, 'entitlement' => 'feature.automations', 'page' => 'automations',
        ],
        'enterprise' => [
            'label' => 'Enterprise Governance', 'category' => 'Administration', 'description' => 'SSO, SCIM, MFA policy, organization governance and ABAC.',
            'dependencies' => [], 'default_enabled' => true, 'entitlement' => null, 'page' => 'enterprise',
        ],
        'platform' => [
            'label' => 'Platform', 'category' => 'Administration', 'description' => 'White-label, add-ons, imports, partners and sandbox workspaces.',
            'dependencies' => [], 'default_enabled' => true, 'entitlement' => null, 'page' => 'platform',
        ],
    ];

    /** @return array<string,mixed>|null */
    /** Handles the definition operation for the current WorkIntel workflow. */ public static function definition(string $key): ?array
    {
        return self::DEFINITIONS[$key] ?? null;
    }

    /** @return array<int,string> */
    /** Handles the keys operation for the current WorkIntel workflow. */ public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /** @return array<int,string> */
    /** Handles the dependencies operation for the current WorkIntel workflow. */ public static function dependencies(string $key): array
    {
        return array_values(self::DEFINITIONS[$key]['dependencies'] ?? []);
    }

    /** @return array<int,string> */
    /** Handles the dependents operation for the current WorkIntel workflow. */ public static function dependents(string $key): array
    {
        $rows = [];
        foreach (self::DEFINITIONS as $moduleKey => $definition) {
            if (in_array($key, $definition['dependencies'] ?? [], true)) $rows[] = $moduleKey;
        }
        return $rows;
    }

    /** Handles the module for page operation for the current WorkIntel workflow. */ public static function moduleForPage(string $page): ?string
    {
        if ($page === 'apps') return 'activity';
        if ($page === 'shifts' || $page === 'schedule') return 'scheduling';
        if ($page === 'leave') return 'attendance';
        foreach (self::DEFINITIONS as $key => $definition) {
            if (($definition['page'] ?? null) === $page) return $key;
        }
        return null;
    }

    /** Handles the module for request operation for the current WorkIntel workflow. */ public static function moduleForRequest(Request $request): ?string
    {
        $path = trim($request->path(), '/');
        foreach (['api/v1/', 'v1/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        $segment = explode('/', $path)[0] ?? '';

        return match ($segment) {
            'people' => 'people',
            'organization' => 'organization',
            'projects' => 'projects',
            'tasks', 'task-workflow' => 'tasks',
            'timesheets', 'time-entries', 'timer' => 'time',
            'attendance', 'leave', 'holidays' => 'attendance',
            'scheduling', 'shifts', 'shift-assignments' => 'scheduling',
            'approvals', 'approval-workflows', 'approval-delegations' => 'approvals',
            'activity-tracking', 'live-workforce' => 'activity',
            'screenshots' => 'screenshots',
            'devices' => 'devices',
            'clients', 'client-invoices', 'client-reports' => 'clients',
            'hris' => 'hris',
            'performance' => 'performance',
            'payroll' => 'payroll',
            'payroll-compliance' => 'payroll-compliance',
            'finance-ops' => 'finance',
            'reports', 'report-runs' => 'reports',
            'documents' => 'documents',
            'website' => 'website',
            'chat' => 'chat',
            'field' => 'field',
            'automations', 'automation-runs', 'automation-dead-letters', 'automation-incoming-hooks' => 'automations',
            'intelligence' => 'intelligence',
            'enterprise' => 'enterprise',
            'platform' => 'platform',
            default => null,
        };
    }
}
