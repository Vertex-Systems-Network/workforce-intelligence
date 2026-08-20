<?php

namespace App\Services\Reporting;

use App\Models\ApplicationSession;
use App\Models\AttendanceRecord;
use App\Models\PayrollItem;
use App\Models\ProductivityRule;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\TimeEntry;
use App\Models\WebsiteSession;
use App\Models\WorkspaceMember;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Provides report query service behavior within the WorkIntel application. */ class ReportQueryService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly ReportCatalog $catalog) {}

    /** Handles the execute operation for the current WorkIntel workflow. */ public function execute(int $workspaceId, WorkspaceMember $viewer, array $configuration): array
    {
        $configuration = $this->normalizeConfiguration($viewer, $configuration);
        $dataset = $configuration['dataset'];
        $definition = $this->catalog->assertCanView($viewer, $dataset);
        [$from, $to] = $this->dateRange($configuration);
        $visibleMemberIds = $this->visibleMemberIds($viewer, $definition['permission_group']);

        $records = match ($dataset) {
            'time_entries' => $this->timeRecords($workspaceId, $visibleMemberIds, $from, $to),
            'attendance' => $this->attendanceRecords($workspaceId, $visibleMemberIds, $from, $to),
            'payroll' => $this->payrollRecords($workspaceId, $visibleMemberIds, $from, $to),
            'activity' => $this->activityRecords($workspaceId, $visibleMemberIds, $from, $to),
            'projects' => $this->projectRecords($workspaceId, $visibleMemberIds, $from, $to),
            'employees' => $this->employeeRecords($workspaceId, $visibleMemberIds, $from, $to),
            default => collect(),
        };

        $records = $this->applyFilters($records, $configuration['filters']);
        $this->assertCurrencySafety($dataset, $configuration, $records);

        $rows = $this->aggregate($records, $configuration['dimensions'], $configuration['metrics']);
        $rows = $this->sortRows($rows, $configuration['sort']);
        $rows = $rows->take($configuration['limit'])->values();

        $columns = $this->columns($definition, $configuration['dimensions'], $configuration['metrics']);
        $summary = [];
        foreach ($configuration['metrics'] as $metric) {
            $summary[$metric] = round((float) $rows->sum(fn (array $row) => (float) ($row[$metric] ?? 0)), 4);
        }

        return [
            'dataset' => $dataset,
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'configuration' => $configuration,
            'columns' => $columns,
            'rows' => $rows->all(),
            'row_count' => $rows->count(),
            'summary' => $summary,
        ];
    }

    /** Handles the normalize configuration operation for the current WorkIntel workflow. */ public function normalizeConfiguration(WorkspaceMember $viewer, array $configuration): array
    {
        $dataset = (string) ($configuration['dataset'] ?? 'time_entries');
        $definition = $this->catalog->assertCanView($viewer, $dataset);
        $dimensionKeys = collect($definition['dimensions'])->pluck('key')->all();
        $metricKeys = collect($definition['metrics'])->pluck('key')->all();

        $dimensions = array_values(array_unique(array_filter((array) ($configuration['dimensions'] ?? $definition['default_dimensions']), fn ($key) => in_array($key, $dimensionKeys, true))));
        $metrics = array_values(array_unique(array_filter((array) ($configuration['metrics'] ?? $definition['default_metrics']), fn ($key) => in_array($key, $metricKeys, true))));

        if (count($dimensions) > 4) throw ValidationException::withMessages(['dimensions' => ['A report can use at most four dimensions.']]);
        if (! $metrics) throw ValidationException::withMessages(['metrics' => ['Select at least one metric.']]);
        if (count($metrics) > 8) throw ValidationException::withMessages(['metrics' => ['A report can use at most eight metrics.']]);

        $today = CarbonImmutable::today();
        $datePreset = in_array(($configuration['date_preset'] ?? 'custom'), ['custom', 'last_7_days', 'last_30_days', 'this_week', 'last_week', 'this_month', 'last_month'], true)
            ? $configuration['date_preset'] : 'custom';
        [$from, $to] = match ($datePreset) {
            'last_7_days' => [$today->subDays(6)->toDateString(), $today->toDateString()],
            'last_30_days' => [$today->subDays(29)->toDateString(), $today->toDateString()],
            'this_week' => [$today->startOfWeek()->toDateString(), $today->endOfWeek()->toDateString()],
            'last_week' => [$today->subWeek()->startOfWeek()->toDateString(), $today->subWeek()->endOfWeek()->toDateString()],
            'this_month' => [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth()->toDateString(), $today->subMonthNoOverflow()->endOfMonth()->toDateString()],
            default => [$configuration['date_from'] ?? $today->subDays(29)->toDateString(), $configuration['date_to'] ?? $today->toDateString()],
        };
        $fromDate = CarbonImmutable::parse($from)->startOfDay();
        $toDate = CarbonImmutable::parse($to)->endOfDay();
        if ($fromDate->gt($toDate)) throw ValidationException::withMessages(['date_from' => ['The start date must be before the end date.']]);
        if ($fromDate->diffInDays($toDate) > (int) config('workintel.reports.max_range_days', 730)) {
            throw ValidationException::withMessages(['date_from' => ['The selected report range is too large.']]);
        }

        $sort = $configuration['sort'] ?? null;
        if (! is_array($sort) || empty($sort['key'])) $sort = ['key' => $metrics[0], 'direction' => 'desc'];
        $allowedSortKeys = [...$dimensions, ...$metrics];
        if (! in_array($sort['key'], $allowedSortKeys, true)) $sort['key'] = $metrics[0];
        $sort['direction'] = strtolower((string) ($sort['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return [
            'dataset' => $dataset,
            'date_preset' => $datePreset,
            'date_from' => $fromDate->toDateString(),
            'date_to' => $toDate->toDateString(),
            'dimensions' => $dimensions,
            'metrics' => $metrics,
            'filters' => is_array($configuration['filters'] ?? null) ? $configuration['filters'] : [],
            'sort' => $sort,
            'limit' => min(max((int) ($configuration['limit'] ?? 5000), 1), (int) config('workintel.reports.max_rows', 20000)),
            'visualization' => $this->normalizeVisualization($configuration['visualization'] ?? [], $dimensions, $metrics),
        ];
    }

    /** Handles the normalize visualization operation for the current WorkIntel workflow. */ private function normalizeVisualization(array $visualization, array $dimensions, array $metrics): array
    {
        $requestedType = $visualization['type'] ?? 'table';
        $type = in_array($requestedType, ['table', 'bar', 'line', 'area'], true) ? $requestedType : 'table';
        return [
            'type' => $type,
            'x' => in_array(($visualization['x'] ?? null), $dimensions, true) ? $visualization['x'] : ($dimensions[0] ?? null),
            'y' => in_array(($visualization['y'] ?? null), $metrics, true) ? $visualization['y'] : ($metrics[0] ?? null),
        ];
    }

    /** Handles the date range operation for the current WorkIntel workflow. */ private function dateRange(array $configuration): array
    {
        return [CarbonImmutable::parse($configuration['date_from'])->startOfDay(), CarbonImmutable::parse($configuration['date_to'])->endOfDay()];
    }

    /** Handles the visible member ids operation for the current WorkIntel workflow. */ private function visibleMemberIds(WorkspaceMember $viewer, string $permissionGroup): array
    {
        $allPermission = match ($permissionGroup) {
            'time' => ['time.view_all'],
            'attendance' => ['people.view_all', 'people.manage'],
            'payroll' => ['payroll.view_all', 'payroll.manage'],
            'activity' => ['activity.view_all', 'activity.manage'],
            'projects' => ['projects.view_all', 'projects.manage', 'projects.view'],
            'people' => ['people.view_all', 'people.manage', 'people.view'],
            default => [],
        };
        foreach ($allPermission as $permission) {
            if ($viewer->hasPermission($permission)) {
                return WorkspaceMember::query()->where('workspace_id', $viewer->workspace_id)->pluck('id')->all();
            }
        }

        $teamPermission = match ($permissionGroup) {
            'time' => ($viewer->hasPermission('time.view_team') || $viewer->hasPermission('time.manage')) ? 'time.view_team' : null, 'attendance' => 'attendance.view_team', 'activity' => 'activity.view_team', default => null,
        };
        if ($teamPermission && $viewer->hasPermission($teamPermission)) {
            $teamIds = $viewer->teams()->pluck('teams.id');
            return WorkspaceMember::query()
                ->where('workspace_id', $viewer->workspace_id)
                ->where(function ($query) use ($viewer, $teamIds) {
                    $query->whereKey($viewer->id)
                        ->orWhere('manager_id', $viewer->id)
                        ->orWhereHas('teams', fn ($teamQuery) => $teamQuery->whereIn('teams.id', $teamIds));
                })
                ->pluck('id')->unique()->values()->all();
        }

        if (in_array($permissionGroup, ['projects', 'people'], true)) {
            return WorkspaceMember::query()->where('workspace_id', $viewer->workspace_id)->pluck('id')->all();
        }

        return [$viewer->id];
    }

    /** Handles the time records operation for the current WorkIntel workflow. */ private function timeRecords(int $workspaceId, array $memberIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return TimeEntry::query()->with(['member.user', 'member.department', 'project.client', 'task'])
            ->where('workspace_id', $workspaceId)->whereIn('member_id', $memberIds)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get()
            ->map(function (TimeEntry $entry) {
                $hours = $entry->duration_seconds / 3600;
                return $this->baseRecord($entry->date, $entry->member, $entry->project_id, $entry->project?->name, $entry->project?->client_id, $entry->project?->client?->name) + [
                    'task' => $entry->task?->title ?? 'Unassigned', 'approval_status' => $entry->approval_status, 'source' => $entry->source,
                    'billable' => $entry->billable ? 'Billable' : 'Non-billable', '_status' => $entry->approval_status, '_source' => $entry->source, '_billable' => $entry->billable,
                    'tracked_hours' => $hours, 'billable_hours' => $entry->billable ? $hours : 0, 'non_billable_hours' => $entry->billable ? 0 : $hours, 'entries' => 1,
                ];
            });
    }

    /** Handles the attendance records operation for the current WorkIntel workflow. */ private function attendanceRecords(int $workspaceId, array $memberIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return AttendanceRecord::query()->with(['member.user', 'member.department'])->where('workspace_id', $workspaceId)->whereIn('member_id', $memberIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get()->map(function (AttendanceRecord $record) {
                return $this->baseRecord($record->date, $record->member) + [
                    'attendance_status' => ucfirst(str_replace('_', ' ', $record->status)), '_status' => $record->status,
                    'worked_hours' => $record->worked_seconds / 3600, 'break_hours' => $record->break_seconds / 3600,
                    'overtime_hours' => $record->overtime_minutes / 60, 'late_minutes' => $record->late_minutes, 'attendance_days' => 1,
                ];
            });
    }

    /** Handles the payroll records operation for the current WorkIntel workflow. */ private function payrollRecords(int $workspaceId, array $memberIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return PayrollItem::query()->with(['member.user', 'member.department', 'run'])
            ->where('workspace_id', $workspaceId)->whereIn('member_id', $memberIds)
            ->whereHas('run', fn ($query) => $query->where('period_end', '>=', $from->toDateString())->where('period_start', '<=', $to->toDateString()))
            ->get()->map(function (PayrollItem $item) {
                $date = $item->run?->period_end ?? $item->created_at;
                return $this->baseRecord($date, $item->member) + [
                    'pay_type' => ucfirst(str_replace('_', ' ', $item->pay_type)), 'payroll_status' => ucfirst($item->run?->status ?? $item->status),
                    'payroll_run' => $item->run?->name ?? 'Payroll', '_status' => $item->run?->status ?? $item->status,
                    'base_pay' => (float) $item->base_pay, 'gross_pay' => (float) $item->gross_pay, 'net_pay' => (float) $item->net_pay,
                    'overtime_pay' => (float) $item->overtime_pay + (float) $item->weekend_pay + (float) $item->holiday_pay,
                    'deductions' => (float) $item->deduction_total + (float) $item->unpaid_leave_deduction,
                    'tax' => (float) $item->tax_total, 'bonuses' => (float) $item->bonus_total + (float) $item->commission_total, 'payroll_items' => 1,
                ];
            });
    }

    /** Handles the activity records operation for the current WorkIntel workflow. */ private function activityRecords(int $workspaceId, array $memberIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $rules = ProductivityRule::query()->where('workspace_id', $workspaceId)->where('active', true)->get();
        $apps = ApplicationSession::query()->with(['member.user', 'member.department', 'member.teams', 'project'])->where('workspace_id', $workspaceId)->whereIn('member_id', $memberIds)->whereBetween('started_at', [$from, $to])->get()
            ->map(function (ApplicationSession $session) use ($rules) {
                [$classification] = $this->classificationFor($rules, 'app', $session->app_key, $session->member, $session->project_id);
                return $this->baseRecord($session->started_at, $session->member, $session->project_id, $session->project?->name) + [
                    'activity_type' => 'Application', 'activity_item' => $session->app_name, 'classification' => ucfirst($classification), '_classification' => $classification,
                    'tracked_hours' => $session->duration_seconds / 3600, 'active_hours' => $session->active_seconds / 3600, 'idle_hours' => $session->idle_seconds / 3600,
                    'productive_hours' => $classification === 'productive' ? $session->duration_seconds / 3600 : 0, 'sessions' => 1,
                ];
            });
        $web = WebsiteSession::query()->with(['member.user', 'member.department', 'member.teams', 'project'])->where('workspace_id', $workspaceId)->whereIn('member_id', $memberIds)->whereBetween('started_at', [$from, $to])->get()
            ->map(function (WebsiteSession $session) use ($rules) {
                [$classification] = $this->classificationFor($rules, 'domain', $session->domain, $session->member, $session->project_id);
                return $this->baseRecord($session->started_at, $session->member, $session->project_id, $session->project?->name) + [
                    'activity_type' => 'Website', 'activity_item' => $session->domain, 'classification' => ucfirst($classification), '_classification' => $classification,
                    'tracked_hours' => $session->duration_seconds / 3600, 'active_hours' => $session->active_seconds / 3600, 'idle_hours' => $session->idle_seconds / 3600,
                    'productive_hours' => $classification === 'productive' ? $session->duration_seconds / 3600 : 0, 'sessions' => 1,
                ];
            });
        return $apps->concat($web)->values();
    }

    /** Handles the project records operation for the current WorkIntel workflow. */ private function projectRecords(int $workspaceId, array $memberIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $projects = Project::query()->with(['client', 'members', 'expenses' => fn ($query) => $query->whereBetween('incurred_on', [$from->toDateString(), $to->toDateString()])])
            ->where('workspace_id', $workspaceId)->where('status', '!=', 'archived')->get();
        $timeEntries = TimeEntry::query()->where('workspace_id', $workspaceId)->whereIn('member_id', $memberIds)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get()->groupBy('project_id');

        return $projects->map(function (Project $project) use ($timeEntries) {
            $entries = $timeEntries->get($project->id, collect());
            $memberRates = $project->members->keyBy('id');
            $tracked = $entries->sum('duration_seconds') / 3600;
            $billable = $entries->where('billable', true)->sum('duration_seconds') / 3600;
            $labor = 0.0; $revenue = 0.0;
            foreach ($entries as $entry) {
                $rate = $memberRates->get($entry->member_id)?->pivot;
                $hours = $entry->duration_seconds / 3600;
                $labor += $hours * (float) ($rate?->hourly_cost ?? 0);
                if ($entry->billable) $revenue += $hours * (float) ($rate?->billing_rate ?? 0);
            }
            $expenses = $project->expenses->where('currency', $project->currency)->sum(fn (ProjectExpense $expense) => (float) $expense->amount);
            return [
                'project' => $project->name, 'client' => $project->client?->name ?? 'Internal', 'project_status' => ucfirst($project->status), 'priority' => ucfirst($project->priority),
                'currency' => $project->currency, '_project_id' => $project->id, '_client_id' => $project->client_id, '_status' => $project->status,
                'tracked_hours' => $tracked, 'billable_hours' => $billable, 'labor_cost' => $labor, 'project_revenue' => $revenue,
                'expenses' => $expenses, 'profit' => $revenue - $labor - $expenses, 'projects_count' => 1,
            ];
        });
    }

    /** Handles the employee records operation for the current WorkIntel workflow. */ private function employeeRecords(int $workspaceId, array $memberIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $members = WorkspaceMember::query()->with(['user', 'department', 'jobTitle'])->where('workspace_id', $workspaceId)->whereIn('id', $memberIds)->get();
        $time = TimeEntry::query()->where('workspace_id', $workspaceId)->whereIn('member_id', $memberIds)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get()->groupBy('member_id');
        $attendance = AttendanceRecord::query()->where('workspace_id', $workspaceId)->whereIn('member_id', $memberIds)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get()->groupBy('member_id');
        $payroll = PayrollItem::query()->with('run')->where('workspace_id', $workspaceId)->whereIn('member_id', $memberIds)
            ->whereHas('run', fn ($query) => $query->where('period_end', '>=', $from->toDateString())->where('period_start', '<=', $to->toDateString()))->get()->groupBy('member_id');

        return $members->map(function (WorkspaceMember $member) use ($time, $attendance, $payroll) {
            $entries = $time->get($member->id, collect()); $days = $attendance->get($member->id, collect()); $payItems = $payroll->get($member->id, collect());
            $memberStatus = $member->status instanceof \BackedEnum ? $member->status->value : (string) $member->status;
            return [
                'employee' => trim($member->user->first_name.' '.$member->user->last_name), 'department' => $member->department?->name ?? 'Unassigned',
                'job_title' => $member->jobTitle?->name ?? $member->job_title ?? 'Unassigned', 'member_status' => ucfirst($memberStatus),
                '_member_id' => $member->id, '_department_id' => $member->department_id, '_status' => $memberStatus,
                'tracked_hours' => $entries->sum('duration_seconds') / 3600, 'billable_hours' => $entries->where('billable', true)->sum('duration_seconds') / 3600,
                'attendance_days' => $days->count(), 'overtime_hours' => $days->sum('overtime_minutes') / 60,
                'projects_count' => $entries->pluck('project_id')->filter()->unique()->count(), 'payroll_net' => $payItems->sum(fn ($item) => (float) $item->net_pay), 'employees_count' => 1,
            ];
        });
    }

    /** Handles the base record operation for the current WorkIntel workflow. */ private function baseRecord(mixed $date, ?WorkspaceMember $member, ?int $projectId = null, ?string $project = null, ?int $clientId = null, ?string $client = null): array
    {
        $carbon = CarbonImmutable::parse($date);
        return [
            'date' => $carbon->toDateString(), 'week' => $carbon->startOfWeek()->format('Y-m-d'), 'month' => $carbon->format('Y-m'),
            'employee' => $member ? trim(($member->user?->first_name ?? '').' '.($member->user?->last_name ?? '')) : 'Unknown',
            'department' => $member?->department?->name ?? 'Unassigned', 'project' => $project ?? 'Unassigned', 'client' => $client ?? 'Internal',
            '_member_id' => $member?->id, '_department_id' => $member?->department_id, '_project_id' => $projectId, '_client_id' => $clientId,
        ];
    }

    /** Handles the apply filters operation for the current WorkIntel workflow. */ private function applyFilters(Collection $records, array $filters): Collection
    {
        $map = ['member_ids' => '_member_id', 'department_ids' => '_department_id', 'project_ids' => '_project_id', 'client_ids' => '_client_id', 'statuses' => '_status', 'sources' => '_source', 'classifications' => '_classification'];
        foreach ($map as $filterKey => $recordKey) {
            $values = array_values(array_filter((array) ($filters[$filterKey] ?? []), fn ($value) => $value !== '' && $value !== null));
            if ($values) $records = $records->filter(fn (array $record) => in_array($record[$recordKey] ?? null, $values, false));
        }
        if (array_key_exists('billable', $filters) && $filters['billable'] !== '' && $filters['billable'] !== null) {
            $expected = filter_var($filters['billable'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($expected !== null) $records = $records->filter(fn (array $record) => ($record['_billable'] ?? null) === $expected);
        }
        return $records->values();
    }

    /** Handles the aggregate operation for the current WorkIntel workflow. */ private function aggregate(Collection $records, array $dimensions, array $metrics): Collection
    {
        if (! $dimensions) {
            $row = [];
            foreach ($metrics as $metric) $row[$metric] = round((float) $records->sum(fn (array $record) => (float) ($record[$metric] ?? 0)), 4);
            return collect([$row]);
        }

        return $records->groupBy(function (array $record) use ($dimensions) {
            return json_encode(array_map(fn (string $dimension) => $record[$dimension] ?? '—', $dimensions), JSON_UNESCAPED_UNICODE);
        })->map(function (Collection $group) use ($dimensions, $metrics) {
            $first = $group->first(); $row = [];
            foreach ($dimensions as $dimension) $row[$dimension] = $first[$dimension] ?? '—';
            foreach ($metrics as $metric) $row[$metric] = round((float) $group->sum(fn (array $record) => (float) ($record[$metric] ?? 0)), 4);
            return $row;
        })->values();
    }

    /** Handles the sort rows operation for the current WorkIntel workflow. */ private function sortRows(Collection $rows, array $sort): Collection
    {
        $key = $sort['key']; $direction = $sort['direction'];
        return $rows->sort(function (array $a, array $b) use ($key, $direction) {
            $left = $a[$key] ?? null; $right = $b[$key] ?? null;
            $comparison = is_numeric($left) && is_numeric($right) ? ((float) $left <=> (float) $right) : strnatcasecmp((string) $left, (string) $right);
            return $direction === 'asc' ? $comparison : -$comparison;
        })->values();
    }

    /** Handles the columns operation for the current WorkIntel workflow. */ private function columns(array $definition, array $dimensions, array $metrics): array
    {
        $dimensionMap = collect($definition['dimensions'])->keyBy('key'); $metricMap = collect($definition['metrics'])->keyBy('key'); $columns = [];
        foreach ($dimensions as $key) $columns[] = ['key' => $key, 'label' => $dimensionMap[$key]['label'] ?? $key, 'type' => 'dimension', 'format' => 'text'];
        foreach ($metrics as $key) $columns[] = ['key' => $key, 'label' => $metricMap[$key]['label'] ?? $key, 'type' => 'metric', 'format' => $metricMap[$key]['format'] ?? 'number'];
        return $columns;
    }

    /** Handles the assert currency safety operation for the current WorkIntel workflow. */ private function assertCurrencySafety(string $dataset, array $configuration, Collection $records): void
    {
        if ($dataset !== 'projects') return;
        $moneyMetrics = ['labor_cost', 'project_revenue', 'expenses', 'profit'];
        if (! array_intersect($moneyMetrics, $configuration['metrics'])) return;
        $currencies = $records->pluck('currency')->filter()->unique();
        if ($currencies->count() > 1 && ! in_array('currency', $configuration['dimensions'], true)) {
            throw ValidationException::withMessages(['dimensions' => ['Project money metrics span multiple currencies. Add Currency as a dimension; automatic FX conversion is intentionally disabled.']]);
        }
    }

    /** Handles the classification for operation for the current WorkIntel workflow. */ private function classificationFor(Collection $rules, string $type, string $target, ?WorkspaceMember $member, ?int $projectId): array
    {
        $teamIds = $member?->teams?->pluck('id')->all() ?? [];
        $candidates = $rules->filter(fn ($rule) => $rule->target_type === $type && strtolower($rule->target) === strtolower($target));
        $scopes = [
            ['project', $projectId], ['member', $member?->id],
            ...array_map(fn ($id) => ['team', $id], $teamIds), ['department', $member?->department_id], ['workspace', null],
        ];
        foreach ($scopes as [$scope, $id]) {
            $rule = $candidates->first(fn ($candidate) => $candidate->scope_type === $scope && ($scope === 'workspace' || (int) $candidate->scope_id === (int) $id));
            if ($rule) return [$rule->classification, $rule->category];
        }
        return ['unclassified', null];
    }
}
