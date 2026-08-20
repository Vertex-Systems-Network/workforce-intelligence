<?php

namespace App\Services\Reporting;

use App\Models\WorkspaceMember;

/** Provides report catalog behavior within the WorkIntel application. */ class ReportCatalog
{
    /**
     * Dataset metadata shared by the API and Report Builder UI.
     */
    /** Handles the all operation for the current WorkIntel workflow. */ public function all(): array
    {
        return [
            'time_entries' => [
                'label' => 'Time Entries',
                'description' => 'Tracked, billable and approved time across employees, projects and tasks.',
                'permission_group' => 'time',
                'dimensions' => $this->dimensions(['date', 'week', 'month', 'employee', 'department', 'project', 'client', 'task', 'approval_status', 'source', 'billable']),
                'metrics' => $this->metrics(['tracked_hours', 'billable_hours', 'non_billable_hours', 'entries']),
                'filters' => $this->filters(['member_ids', 'department_ids', 'project_ids', 'client_ids', 'statuses', 'sources', 'billable']),
                'default_dimensions' => ['month'],
                'default_metrics' => ['tracked_hours', 'billable_hours'],
            ],
            'attendance' => [
                'label' => 'Attendance',
                'description' => 'Presence, worked hours, late arrivals, breaks and overtime.',
                'permission_group' => 'attendance',
                'dimensions' => $this->dimensions(['date', 'week', 'month', 'employee', 'department', 'attendance_status']),
                'metrics' => $this->metrics(['worked_hours', 'break_hours', 'overtime_hours', 'late_minutes', 'attendance_days']),
                'filters' => $this->filters(['member_ids', 'department_ids', 'statuses']),
                'default_dimensions' => ['month'],
                'default_metrics' => ['worked_hours', 'overtime_hours'],
            ],
            'payroll' => [
                'label' => 'Payroll',
                'description' => 'Approved payroll cost, earnings, deductions and net pay.',
                'permission_group' => 'payroll',
                'dimensions' => $this->dimensions(['month', 'employee', 'department', 'pay_type', 'payroll_status', 'payroll_run']),
                'metrics' => $this->metrics(['base_pay', 'gross_pay', 'net_pay', 'overtime_pay', 'deductions', 'tax', 'bonuses', 'payroll_items']),
                'filters' => $this->filters(['member_ids', 'department_ids', 'statuses']),
                'default_dimensions' => ['month'],
                'default_metrics' => ['gross_pay', 'net_pay'],
            ],
            'activity' => [
                'label' => 'Activity',
                'description' => 'Application and domain usage with active/idle and productivity classification.',
                'permission_group' => 'activity',
                'dimensions' => $this->dimensions(['date', 'week', 'month', 'employee', 'department', 'activity_type', 'activity_item', 'classification', 'project']),
                'metrics' => $this->metrics(['tracked_hours', 'active_hours', 'idle_hours', 'productive_hours', 'sessions']),
                'filters' => $this->filters(['member_ids', 'department_ids', 'project_ids', 'classifications']),
                'default_dimensions' => ['month'],
                'default_metrics' => ['tracked_hours', 'active_hours'],
            ],
            'projects' => [
                'label' => 'Projects',
                'description' => 'Project hours, labor cost, revenue, expenses and profitability.',
                'permission_group' => 'projects',
                'dimensions' => $this->dimensions(['project', 'client', 'project_status', 'priority', 'currency']),
                'metrics' => $this->metrics(['tracked_hours', 'billable_hours', 'labor_cost', 'project_revenue', 'expenses', 'profit', 'projects_count']),
                'filters' => $this->filters(['project_ids', 'client_ids', 'statuses']),
                'default_dimensions' => ['project'],
                'default_metrics' => ['tracked_hours', 'project_revenue', 'profit'],
            ],
            'employees' => [
                'label' => 'Employees',
                'description' => 'Employee-level work, attendance, project participation and payroll totals.',
                'permission_group' => 'people',
                'dimensions' => $this->dimensions(['employee', 'department', 'job_title', 'member_status']),
                'metrics' => $this->metrics(['tracked_hours', 'billable_hours', 'attendance_days', 'overtime_hours', 'projects_count', 'payroll_net', 'employees_count']),
                'filters' => $this->filters(['member_ids', 'department_ids', 'statuses']),
                'default_dimensions' => ['employee'],
                'default_metrics' => ['tracked_hours', 'attendance_days'],
            ],
        ];
    }

    /** Handles the available for operation for the current WorkIntel workflow. */ public function availableFor(WorkspaceMember $viewer): array
    {
        return collect($this->all())
            ->filter(fn (array $dataset) => $this->canViewDataset($viewer, $dataset['permission_group']))
            ->map(fn (array $dataset, string $key) => ['key' => $key, ...$dataset])
            ->values()
            ->all();
    }

    /** Handles the available map for operation for the current WorkIntel workflow. */ public function availableMapFor(WorkspaceMember $viewer): array
    {
        return collect($this->all())
            ->filter(fn (array $dataset) => $this->canViewDataset($viewer, $dataset['permission_group']))
            ->all();
    }

    /** Returns get data required by the current workflow. */ public function get(string $dataset): ?array
    {
        return $this->all()[$dataset] ?? null;
    }

    /** Handles the assert can view operation for the current WorkIntel workflow. */ public function assertCanView(WorkspaceMember $viewer, string $dataset): array
    {
        $definition = $this->get($dataset);
        abort_unless($definition, 422, 'Unknown report dataset.');
        abort_unless($this->canViewDataset($viewer, $definition['permission_group']), 403, 'You do not have permission to view this report dataset.');
        return $definition;
    }

    /** Determines whether the can manage condition is satisfied. */ public function canManage(WorkspaceMember $viewer): bool
    {
        return $viewer->hasPermission('reports.manage');
    }

    /** Determines whether the can view dataset condition is satisfied. */ private function canViewDataset(WorkspaceMember $viewer, string $group): bool
    {
        return match ($group) {
            'time' => $viewer->hasPermission('time.view_own') || $viewer->hasPermission('time.view_team') || $viewer->hasPermission('time.view_all') || $viewer->hasPermission('time.manage'),
            'attendance' => $viewer->hasPermission('attendance.view_own') || $viewer->hasPermission('attendance.view_team') || $viewer->hasPermission('attendance.manage'),
            'payroll' => $viewer->hasPermission('payroll.view_all') || $viewer->hasPermission('payroll.manage'),
            'activity' => $viewer->hasPermission('activity.view_own') || $viewer->hasPermission('activity.view_team') || $viewer->hasPermission('activity.view_all') || $viewer->hasPermission('activity.manage'),
            'projects' => $viewer->hasPermission('projects.view_all') || $viewer->hasPermission('projects.manage') || $viewer->hasPermission('projects.view'),
            'people' => $viewer->hasPermission('people.view_all') || $viewer->hasPermission('people.manage') || $viewer->hasPermission('people.view'),
            default => false,
        };
    }


    /** Handles the filters operation for the current WorkIntel workflow. */ private function filters(array $keys): array
    {
        $definitions = [
            'member_ids' => ['Employees', 'members', 'multi'], 'department_ids' => ['Departments', 'departments', 'multi'],
            'project_ids' => ['Projects', 'projects', 'multi'], 'client_ids' => ['Clients', 'clients', 'multi'],
            'statuses' => ['Status', 'statuses', 'multi'], 'sources' => ['Source', 'sources', 'multi'],
            'classifications' => ['Classification', 'classifications', 'multi'], 'billable' => ['Billable', 'boolean', 'boolean'],
        ];
        return array_map(function (string $key) use ($definitions) {
            [$label, $source, $type] = $definitions[$key] ?? [ucwords(str_replace('_', ' ', $key)), $key, 'multi'];
            return ['key' => $key, 'label' => $label, 'source' => $source, 'type' => $type];
        }, $keys);
    }

    /** Handles the dimensions operation for the current WorkIntel workflow. */ private function dimensions(array $keys): array
    {
        $labels = [
            'date' => 'Date', 'week' => 'Week', 'month' => 'Month', 'employee' => 'Employee', 'department' => 'Department',
            'project' => 'Project', 'client' => 'Client', 'task' => 'Task', 'approval_status' => 'Approval Status', 'source' => 'Source',
            'billable' => 'Billable', 'attendance_status' => 'Attendance Status', 'pay_type' => 'Pay Type', 'payroll_status' => 'Payroll Status',
            'payroll_run' => 'Payroll Run', 'activity_type' => 'Activity Type', 'activity_item' => 'Application / Domain', 'classification' => 'Classification',
            'project_status' => 'Project Status', 'priority' => 'Priority', 'currency' => 'Currency', 'job_title' => 'Job Title', 'member_status' => 'Employee Status',
        ];
        return array_map(fn (string $key) => ['key' => $key, 'label' => $labels[$key] ?? ucwords(str_replace('_', ' ', $key))], $keys);
    }

    /** Handles the metrics operation for the current WorkIntel workflow. */ private function metrics(array $keys): array
    {
        $definitions = [
            'tracked_hours' => ['Tracked Hours', 'hours'], 'billable_hours' => ['Billable Hours', 'hours'], 'non_billable_hours' => ['Non-Billable Hours', 'hours'],
            'entries' => ['Entries', 'number'], 'worked_hours' => ['Worked Hours', 'hours'], 'break_hours' => ['Break Hours', 'hours'],
            'overtime_hours' => ['Overtime Hours', 'hours'], 'late_minutes' => ['Late Minutes', 'number'], 'attendance_days' => ['Attendance Days', 'number'],
            'base_pay' => ['Base Pay', 'money'], 'gross_pay' => ['Gross Pay', 'money'], 'net_pay' => ['Net Pay', 'money'], 'overtime_pay' => ['Overtime Pay', 'money'],
            'deductions' => ['Deductions', 'money'], 'tax' => ['Tax / Withholding', 'money'], 'bonuses' => ['Bonuses & Commission', 'money'], 'payroll_items' => ['Payroll Items', 'number'],
            'active_hours' => ['Active Hours', 'hours'], 'idle_hours' => ['Idle Hours', 'hours'], 'productive_hours' => ['Productive Hours', 'hours'], 'sessions' => ['Sessions', 'number'],
            'labor_cost' => ['Labor Cost', 'money'], 'project_revenue' => ['Estimated Revenue', 'money'], 'expenses' => ['Expenses', 'money'], 'profit' => ['Profit', 'money'],
            'projects_count' => ['Projects', 'number'], 'employees_count' => ['Employees', 'number'], 'payroll_net' => ['Net Payroll', 'money'],
        ];
        return array_map(function (string $key) use ($definitions) {
            [$label, $format] = $definitions[$key] ?? [ucwords(str_replace('_', ' ', $key)), 'number'];
            return ['key' => $key, 'label' => $label, 'format' => $format];
        }, $keys);
    }
}
