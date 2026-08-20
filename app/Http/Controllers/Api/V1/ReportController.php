<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\EntitlementService;
use App\Models\Client;
use App\Models\Department;
use App\Models\Project;
use App\Models\ReportExport;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use App\Models\SavedReport;
use App\Models\WorkspaceMember;
use App\Services\Reporting\ReportCatalog;
use App\Services\Reporting\ReportExecutionService;
use App\Services\Reporting\ReportExportService;
use App\Services\Reporting\ReportQueryService;
use App\Services\Reporting\ReportScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Provides report controller behavior within the WorkIntel application. */ class ReportController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(
        private readonly ReportCatalog $catalog,
        private readonly ReportQueryService $query,
        private readonly ReportExecutionService $execution,
        private readonly ReportExportService $exports,
        private readonly ReportScheduleService $schedules,
    ) {}

    /** Handles the catalog operation for the current WorkIntel workflow. */ public function catalog(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $datasets = collect($this->catalog->availableMapFor($viewer))->map(fn (array $definition, string $key) => ['key' => $key, ...$definition])->values();
        $memberOptions = $this->optionMembers($viewer);

        return response()->json([
            'datasets' => $datasets,
            'can_manage' => $this->catalog->canManage($viewer),
            'options' => [
                'members' => $memberOptions->map(fn ($member) => ['id' => $member->id, 'name' => trim($member->user->first_name.' '.$member->user->last_name)])->values(),
                'departments' => Department::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(['id', 'name']),
                'projects' => Project::query()->where('workspace_id', $workspace->id)->where('status', '!=', 'archived')->orderBy('name')->get(['id', 'name']),
                'clients' => Client::query()->where('workspace_id', $workspace->id)->where('status', '!=', 'archived')->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    /** Handles the preview operation for the current WorkIntel workflow. */ public function preview(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $configuration = $request->validate(['configuration' => ['required', 'array']])['configuration'];
        $result = $this->query->execute($workspace->id, $viewer, $configuration);
        $result['rows'] = array_slice($result['rows'], 0, (int) config('workintel.reports.preview_rows', 200));
        return response()->json($result);
    }

    /** Handles the saved operation for the current WorkIntel workflow. */ public function saved(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $manage = $this->catalog->canManage($viewer);
        $rows = SavedReport::query()->withCount('schedules')->with('creator:id,first_name,last_name')->where('workspace_id', $workspace->id)
            ->when(! $manage, fn ($query) => $query->where(fn ($scope) => $scope->where('is_shared', true)->orWhere('created_by', $request->user()->id)))
            ->latest('updated_at')->get()->filter(function (SavedReport $report) use ($viewer) {
                try { $this->catalog->assertCanView($viewer, $report->dataset); return true; } catch (\Throwable) { return false; }
            })->values();
        return response()->json(['data' => $rows]);
    }

    /** Handles the store saved operation for the current WorkIntel workflow. */ public function storeSaved(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        app(EntitlementService::class)->assertWithinLimit($workspace, 'saved_reports', SavedReport::query()->where('workspace_id', $workspace->id)->count());
        $viewer = $request->attributes->get('workspaceMember');
        $data = $this->validateSaved($request, $viewer);
        $configuration = $this->query->normalizeConfiguration($viewer, $data['configuration']);
        $report = SavedReport::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $workspace->id, 'created_by' => $request->user()->id,
            'name' => $data['name'], 'description' => $data['description'] ?? null, 'dataset' => $configuration['dataset'],
            'configuration' => $configuration, 'is_shared' => $data['is_shared'] ?? true,
        ]);
        return response()->json(['data' => $report], 201);
    }

    /** Updates update saved data for the requested resource. */ public function updateSaved(Request $request, SavedReport $savedReport): JsonResponse
    {
        $workspace = $request->attributes->get('workspace'); $viewer = $request->attributes->get('workspaceMember');
        $this->assertWorkspace($workspace->id, $savedReport->workspace_id);
        $data = $this->validateSaved($request, $viewer);
        $configuration = $this->query->normalizeConfiguration($viewer, $data['configuration']);
        $savedReport->update(['name' => $data['name'], 'description' => $data['description'] ?? null, 'dataset' => $configuration['dataset'], 'configuration' => $configuration, 'is_shared' => $data['is_shared'] ?? true]);
        return response()->json(['data' => $savedReport->fresh()]);
    }

    /** Handles the destroy saved operation for the current WorkIntel workflow. */ public function destroySaved(Request $request, SavedReport $savedReport): JsonResponse
    {
        $workspace = $request->attributes->get('workspace'); $this->assertWorkspace($workspace->id, $savedReport->workspace_id); $savedReport->delete();
        return response()->json(null, 204);
    }

    /** Handles the run saved operation for the current WorkIntel workflow. */ public function runSaved(Request $request, SavedReport $savedReport): JsonResponse
    {
        $workspace = $request->attributes->get('workspace'); $viewer = $request->attributes->get('workspaceMember');
        $this->assertWorkspace($workspace->id, $savedReport->workspace_id); $this->assertSavedAccess($request, $viewer, $savedReport); $this->catalog->assertCanView($viewer, $savedReport->dataset);
        $run = $this->execution->runConfiguration($workspace->id, $viewer, $savedReport->name, $savedReport->configuration, $savedReport);
        return response()->json(['data' => $this->runPayload($run)], 201);
    }

    /** Handles the run ad hoc operation for the current WorkIntel workflow. */ public function runAdHoc(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace'); $viewer = $request->attributes->get('workspaceMember');
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'configuration' => ['required', 'array']]);
        $run = $this->execution->runConfiguration($workspace->id, $viewer, $data['name'], $data['configuration']);
        return response()->json(['data' => $this->runPayload($run)], 201);
    }

    /** Handles the runs operation for the current WorkIntel workflow. */ public function runs(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace'); $viewer = $request->attributes->get('workspaceMember');
        $manage = $this->catalog->canManage($viewer);
        $userId = $request->user()->id;
        $rows = ReportRun::query()->with(['savedReport:id,name,is_shared,created_by', 'requester:id,first_name,last_name', 'exports'])->where('workspace_id', $workspace->id)->latest()->limit(100)->get()
            ->filter(function (ReportRun $run) use ($viewer, $manage, $userId) {
                try { $this->catalog->assertCanView($viewer, $run->dataset); } catch (\Throwable) { return false; }
                if ($manage) return true;
                if ($run->saved_report_id) return $run->savedReport && ($run->savedReport->is_shared || $run->savedReport->created_by === $userId);
                return $run->requested_by === $userId;
            })->values();
        return response()->json(['data' => $rows->map(fn ($run) => $this->runPayload($run))->values()]);
    }

    /** Handles the show run operation for the current WorkIntel workflow. */ public function showRun(Request $request, ReportRun $reportRun): JsonResponse
    {
        $workspace = $request->attributes->get('workspace'); $viewer = $request->attributes->get('workspaceMember');
        $this->assertWorkspace($workspace->id, $reportRun->workspace_id); $this->assertRunAccess($request, $viewer, $reportRun); $this->catalog->assertCanView($viewer, $reportRun->dataset);
        return response()->json(['data' => $this->runPayload($reportRun->load('exports'), true)]);
    }

    /** Creates create export data for the requested workflow. */ public function createExport(Request $request, ReportRun $reportRun): JsonResponse
    {
        $workspace = $request->attributes->get('workspace'); $viewer = $request->attributes->get('workspaceMember');
        $this->assertWorkspace($workspace->id, $reportRun->workspace_id); $this->assertRunAccess($request, $viewer, $reportRun); $this->catalog->assertCanView($viewer, $reportRun->dataset);
        $format = $request->validate(['format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])]])['format'];
        $export = $this->exports->create($reportRun, $format, $request->user()->id);
        return response()->json(['data' => $export], 201);
    }

    /** Handles the download export operation for the current WorkIntel workflow. */ public function downloadExport(Request $request, ReportExport $reportExport)
    {
        $workspace = $request->attributes->get('workspace'); $viewer = $request->attributes->get('workspaceMember');
        $this->assertWorkspace($workspace->id, $reportExport->workspace_id); $reportExport->loadMissing('run'); $this->assertRunAccess($request, $viewer, $reportExport->run); $this->catalog->assertCanView($viewer, $reportExport->run->dataset);
        abort_unless($reportExport->status === 'completed' && $reportExport->path && Storage::disk($reportExport->disk)->exists($reportExport->path), 404);
        return Storage::disk($reportExport->disk)->download($reportExport->path, $reportExport->filename, ['Content-Type' => $reportExport->mime_type]);
    }

    /** Handles the schedules operation for the current WorkIntel workflow. */ public function schedules(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        return response()->json(['data' => ReportSchedule::query()->with('savedReport:id,name,dataset')->where('workspace_id', $workspace->id)->orderByDesc('active')->orderBy('next_run_at')->get()]);
    }

    /** Handles the store schedule operation for the current WorkIntel workflow. */ public function storeSchedule(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        app(EntitlementService::class)->assertWithinLimit($workspace, 'scheduled_reports', ReportSchedule::query()->where('workspace_id', $workspace->id)->where('active', true)->count()); $viewer = $request->attributes->get('workspaceMember'); $data = $this->validateSchedule($request, $workspace->id);
        $saved = SavedReport::query()->where('workspace_id', $workspace->id)->findOrFail($data['saved_report_id']);
        $this->catalog->assertCanView($viewer, $saved->dataset);
        $schedule = ReportSchedule::create(['uuid' => (string) Str::uuid(), 'workspace_id' => $workspace->id, 'created_by' => $request->user()->id, ...$data]);
        if ($schedule->active) $schedule->update(['next_run_at' => $this->schedules->calculateNextRun($schedule->frequency, $schedule->time_of_day, $schedule->timezone, $schedule->day_of_week, $schedule->day_of_month)]);
        return response()->json(['data' => $schedule->fresh('savedReport')], 201);
    }

    /** Updates update schedule data for the requested resource. */ public function updateSchedule(Request $request, ReportSchedule $reportSchedule): JsonResponse
    {
        $workspace = $request->attributes->get('workspace'); $viewer = $request->attributes->get('workspaceMember'); $this->assertWorkspace($workspace->id, $reportSchedule->workspace_id); $data = $this->validateSchedule($request, $workspace->id);
        if (! $reportSchedule->active && ($data['active'] ?? false)) {
            app(EntitlementService::class)->assertWithinLimit($workspace, 'scheduled_reports', ReportSchedule::query()->where('workspace_id', $workspace->id)->where('active', true)->count());
        }
        $saved = SavedReport::query()->where('workspace_id', $workspace->id)->findOrFail($data['saved_report_id']);
        $this->catalog->assertCanView($viewer, $saved->dataset);
        $reportSchedule->update($data);
        $reportSchedule->update(['next_run_at' => $reportSchedule->active ? $this->schedules->calculateNextRun($reportSchedule->frequency, $reportSchedule->time_of_day, $reportSchedule->timezone, $reportSchedule->day_of_week, $reportSchedule->day_of_month) : null]);
        return response()->json(['data' => $reportSchedule->fresh('savedReport')]);
    }

    /** Handles the destroy schedule operation for the current WorkIntel workflow. */ public function destroySchedule(Request $request, ReportSchedule $reportSchedule): JsonResponse
    {
        $workspace = $request->attributes->get('workspace'); $this->assertWorkspace($workspace->id, $reportSchedule->workspace_id); $reportSchedule->delete(); return response()->json(null, 204);
    }

    /** Handles the run schedule now operation for the current WorkIntel workflow. */ public function runScheduleNow(Request $request, ReportSchedule $reportSchedule): JsonResponse
    {
        $workspace = $request->attributes->get('workspace'); $this->assertWorkspace($workspace->id, $reportSchedule->workspace_id);
        $this->schedules->runSchedule($reportSchedule); return response()->json(['data' => $reportSchedule->fresh('savedReport')]);
    }

    /** Validates validate saved input before it is processed. */ private function validateSaved(Request $request, WorkspaceMember $viewer): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:1000'], 'is_shared' => ['nullable', 'boolean'], 'configuration' => ['required', 'array']]);
    }

    /** Validates validate schedule input before it is processed. */ private function validateSchedule(Request $request, int $workspaceId): array
    {
        return $request->validate([
            'saved_report_id' => ['required', Rule::exists('saved_reports', 'id')->where('workspace_id', $workspaceId)], 'name' => ['required', 'string', 'max:160'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])], 'time_of_day' => ['required', 'date_format:H:i'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'], 'day_of_month' => ['nullable', 'integer', 'between:1,28'],
            'timezone' => ['required', 'timezone'], 'export_format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])], 'active' => ['required', 'boolean'],
        ]);
    }


    /** Handles the option members operation for the current WorkIntel workflow. */ private function optionMembers(WorkspaceMember $viewer)
    {
        $allPermissions = ['people.manage', 'people.view_all', 'people.view', 'time.view_all', 'activity.view_all', 'activity.manage', 'payroll.view_all', 'payroll.manage'];
        if (collect($allPermissions)->contains(fn (string $permission) => $viewer->hasPermission($permission))) {
            return WorkspaceMember::query()->with('user')->where('workspace_id', $viewer->workspace_id)->where('status', 'active')->orderBy('id')->get();
        }
        $teamPermissions = ['people.view_team', 'time.view_team', 'time.manage', 'attendance.view_team', 'activity.view_team'];
        if (collect($teamPermissions)->contains(fn (string $permission) => $viewer->hasPermission($permission))) {
            $teamIds = $viewer->teams()->pluck('teams.id');
            return WorkspaceMember::query()->with('user')->where('workspace_id', $viewer->workspace_id)->where('status', 'active')
                ->where(fn ($query) => $query->whereKey($viewer->id)->orWhere('manager_id', $viewer->id)->orWhereHas('teams', fn ($teamQuery) => $teamQuery->whereIn('teams.id', $teamIds)))->get();
        }
        $ownPermissions = ['time.view_own', 'attendance.view_own', 'activity.view_own'];
        if (collect($ownPermissions)->contains(fn (string $permission) => $viewer->hasPermission($permission))) {
            return WorkspaceMember::query()->with('user')->whereKey($viewer->id)->get();
        }
        return collect();
    }

    /** Handles the assert saved access operation for the current WorkIntel workflow. */ private function assertSavedAccess(Request $request, WorkspaceMember $viewer, SavedReport $savedReport): void
    {
        if ($this->catalog->canManage($viewer)) return;
        abort_unless($savedReport->is_shared || $savedReport->created_by === $request->user()->id, 404);
    }

    /** Handles the assert run access operation for the current WorkIntel workflow. */ private function assertRunAccess(Request $request, WorkspaceMember $viewer, ReportRun $run): void
    {
        if ($this->catalog->canManage($viewer)) return;
        if ($run->saved_report_id) {
            $saved = $run->savedReport()->first();
            abort_unless($saved && ($saved->is_shared || $saved->created_by === $request->user()->id), 404);
            return;
        }
        abort_unless($run->requested_by === $request->user()->id, 404);
    }

    /** Handles the run payload operation for the current WorkIntel workflow. */ private function runPayload(ReportRun $run, bool $withRows = false): array
    {
        return [
            'id' => $run->id, 'uuid' => $run->uuid, 'name' => $run->name, 'dataset' => $run->dataset, 'configuration' => $run->configuration,
            'status' => $run->status, 'row_count' => $run->row_count, 'columns' => $run->columns, 'summary' => $run->summary,
            'rows' => $withRows ? $run->rows() : null, 'error_message' => $run->error_message,
            'started_at' => optional($run->started_at)->toIso8601String(), 'completed_at' => optional($run->completed_at)->toIso8601String(), 'created_at' => optional($run->created_at)->toIso8601String(),
            'exports' => $run->relationLoaded('exports') ? $run->exports : [],
        ];
    }

    /** Handles the assert workspace operation for the current WorkIntel workflow. */ private function assertWorkspace(int $workspaceId, int $resourceWorkspaceId): void { abort_unless($workspaceId === $resourceWorkspaceId, 404); }
}
