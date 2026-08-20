<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\TimesheetPeriod;
use App\Models\TimesheetAction;
use App\Models\WorkspaceMember;
use App\Services\Access\WorkScopeService;
use App\Services\Approvals\ApprovalEngine;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Provides timesheet controller behavior within the WorkIntel application. */ class TimesheetController extends Controller
{
    /** Handles the week operation for the current WorkIntel workflow. */ public function week(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $currentMember = $request->attributes->get('workspaceMember');
        $visibleMemberIds = $this->visibleMemberIds($currentMember);
        $requestedStart = $request->query('start');
        $start = $requestedStart ? Carbon::parse($requestedStart)->startOfDay() : now($workspace->timezone)->startOfWeek();
        $end = $start->copy()->addDays(6)->endOfDay();

        $entries = TimeEntry::query()
            ->with(['member.user:id,first_name,last_name', 'project:id,name', 'task:id,title'])
            ->where('workspace_id', $workspace->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->whereIn('member_id', $visibleMemberIds)
            ->orderBy('started_at')
            ->get();

        $members = WorkspaceMember::query()
            ->with('user:id,first_name,last_name')
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->whereIn('id', $visibleMemberIds)
            ->orderBy('id')
            ->get();

        $periods = TimesheetPeriod::query()
            ->where('workspace_id', $workspace->id)
            ->whereDate('week_start', $start->toDateString())
            ->whereIn('member_id', $members->pluck('id'))
            ->get()->keyBy('member_id');

        $days = collect(range(0, 6))->map(fn (int $offset) => $start->copy()->addDays($offset));

        $rows = $members->map(function (WorkspaceMember $member) use ($entries, $days, $periods) {
            $memberEntries = $entries->where('member_id', $member->id);
            $daily = $days->mapWithKeys(function (Carbon $day) use ($memberEntries) {
                return [$day->toDateString() => (int) $memberEntries->filter(
                    fn (TimeEntry $entry) => $entry->date->toDateString() === $day->toDateString()
                )->sum('duration_seconds')];
            });
            $period = $periods->get($member->id);

            return [
                'member_id' => $member->id,
                'name' => trim($member->user->first_name.' '.$member->user->last_name),
                'job_title' => $member->job_title,
                'days' => $daily,
                'total_seconds' => (int) $memberEntries->sum('duration_seconds'),
                'pending_count' => $memberEntries->whereIn('approval_status', ['draft', 'submitted'])->count(),
                'period_id' => $period?->id,
                'period_status' => $period?->status ?? 'open',
            ];
        });

        $projectQuery = Project::query()->where('workspace_id', $workspace->id)->where('status', '!=', 'archived');
        app(WorkScopeService::class)->scopeProjects($projectQuery, $currentMember);
        $projects = $projectQuery->orderBy('name')->get(['id', 'name']);
        $taskQuery = Task::query()->where('workspace_id', $workspace->id);
        app(WorkScopeService::class)->scopeTasks($taskQuery, $currentMember);
        $tasks = $taskQuery->orderBy('title')->get(['id', 'project_id', 'title']);
        $history = TimesheetAction::query()
            ->with(['actor:id,first_name,last_name', 'member.user:id,first_name,last_name'])
            ->where('workspace_id', $workspace->id)
            ->whereIn('member_id', $members->pluck('id'))
            ->where(function ($query) use ($periods, $start, $end) {
                $periodIds = $periods->pluck('id')->filter()->values();
                if ($periodIds->isNotEmpty()) $query->whereIn('timesheet_period_id', $periodIds);
                $query->orWhereBetween('created_at', [$start, $end->copy()->addDays(7)]);
            })
            ->orderByDesc('created_at')->limit(80)->get();

        return response()->json([
            'week_start' => $start->toDateString(),
            'week_end' => $end->toDateString(),
            'days' => $days->map(fn (Carbon $day) => ['date' => $day->toDateString(), 'label' => $day->format('D M j')])->values(),
            'summary' => $this->summary($entries),
            'rows' => $rows,
            'entries' => $entries->map(fn (TimeEntry $entry) => $this->entryPayload($entry))->values(),
            'projects' => $projects,
            'tasks' => $tasks,
            'current_member_id' => $currentMember->id,
            'can_manage' => $this->canManageTime($currentMember),
            'history' => $history,
        ]);
    }

    /** Handles the store entry operation for the current WorkIntel workflow. */ public function storeEntry(Request $request): JsonResponse
    {
        [$workspace, $member, $data] = $this->validatedManualEntry($request);
        $start = Carbon::parse($data['started_at']);
        $end = Carbon::parse($data['ended_at']);
        $this->ensurePeriodEditable($workspace->id, $member->id, $start);
        [$project, $task] = $this->relatedWork($member, $workspace->id, $data['project_id'] ?? null, $data['task_id'] ?? null);
        $this->ensureNoOverlap($workspace->id, $member->id, $start, $end);

        $entry = TimeEntry::create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'project_id' => $project?->id,
            'task_id' => $task?->id,
            'date' => $start->toDateString(),
            'started_at' => $start,
            'ended_at' => $end,
            'duration_seconds' => (int) $start->diffInSeconds($end),
            'billable' => $data['billable'] ?? true,
            'source' => 'manual',
            'approval_status' => 'draft',
            'note' => $data['note'] ?? null,
        ]);

        return response()->json(['data' => $this->entryPayload($entry->load(['member.user', 'project', 'task']))], 201);
    }

    /** Updates update entry data for the requested resource. */ public function updateEntry(Request $request, TimeEntry $entry): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $currentMember = $request->attributes->get('workspaceMember');
        abort_unless($entry->workspace_id === $workspace->id, 404);
        $this->assertCanEditMember($currentMember, $entry->member_id);
        abort_if($entry->approval_status === 'approved', 422, 'Approved time cannot be edited.');

        $data = $this->manualRules($request);
        $start = Carbon::parse($data['started_at']);
        $end = Carbon::parse($data['ended_at']);
        $this->ensurePeriodEditable($workspace->id, $entry->member_id, $start);
        [$project, $task] = $this->relatedWork($currentMember, $workspace->id, $data['project_id'] ?? null, $data['task_id'] ?? null);
        $this->ensureNoOverlap($workspace->id, $entry->member_id, $start, $end, $entry->id);

        $entry->update([
            'project_id' => $project?->id,
            'task_id' => $task?->id,
            'date' => $start->toDateString(),
            'started_at' => $start,
            'ended_at' => $end,
            'duration_seconds' => (int) $start->diffInSeconds($end),
            'billable' => $data['billable'] ?? true,
            'note' => $data['note'] ?? null,
            'approval_status' => 'draft',
        ]);

        return response()->json(['data' => $this->entryPayload($entry->fresh()->load(['member.user', 'project', 'task']))]);
    }

    /** Handles the destroy entry operation for the current WorkIntel workflow. */ public function destroyEntry(Request $request, TimeEntry $entry): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $currentMember = $request->attributes->get('workspaceMember');
        abort_unless($entry->workspace_id === $workspace->id, 404);
        $this->assertCanEditMember($currentMember, $entry->member_id);
        abort_if($entry->approval_status === 'approved', 422, 'Approved time cannot be deleted.');
        $this->ensurePeriodEditable($workspace->id, $entry->member_id, $entry->date);
        $entry->delete();
        return response()->json(['message' => 'Time entry deleted.']);
    }

    /** Handles the submit week operation for the current WorkIntel workflow. */ public function submitWeek(Request $request, ApprovalEngine $approvals): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        $data = $request->validate(['week_start' => ['required', 'date']]);
        $start = Carbon::parse($data['week_start'])->startOfDay();
        $end = $start->copy()->addDays(6);
        $period = $this->periodForMemberWeek(
            $workspace->id,
            $member->id,
            $start,
            'open'
        );
        abort_if(in_array($period->status, ['locked', 'approved'], true), 422, 'This timesheet is already finalized.');

        $previousStatus = $period->status;
        TimeEntry::query()->where('workspace_id', $workspace->id)->where('member_id', $member->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())->where('approval_status', 'draft')
            ->update(['approval_status' => 'submitted', 'updated_at' => now()]);
        $period->update(['status' => 'submitted', 'submitted_at' => now()]);
        $this->recordAction($request, $member->id, 'submitted', $previousStatus, 'submitted', $period);
        $manualCount = TimeEntry::query()->where('workspace_id', $workspace->id)->where('member_id', $member->id)
            ->whereDate('date', '>=', $start->toDateString())->whereDate('date', '<=', $end->toDateString())->where('source', 'manual')->count();
        $approvals->submitFor(
            $workspace, $member, 'timesheet.submitted', 'timesheet_period', $period,
            ['department_id' => $member->department_id, 'manual_time_count' => $manualCount, 'week_start' => $start->toDateString()],
            'Timesheet · '.trim($member->user?->first_name.' '.$member->user?->last_name),
            $start->toDateString().' → '.$end->toDateString().' · '.$manualCount.' manual entr'.($manualCount === 1 ? 'y' : 'ies')
        );

        return response()->json(['data' => $period->fresh()]);
    }

    /** Handles the lock period operation for the current WorkIntel workflow. */ public function lockPeriod(Request $request, TimesheetPeriod $period, ApprovalEngine $approvals): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($period->workspace_id === $workspace->id, 404);
        $this->assertCanManageMember($request->attributes->get('workspaceMember'), $period->member_id);
        $pending = TimeEntry::query()->where('workspace_id', $workspace->id)->where('member_id', $period->member_id)
            ->whereBetween('date', [$period->week_start->toDateString(), $period->week_end->toDateString()])
            ->whereIn('approval_status', ['draft', 'submitted'])->exists();
        abort_if($pending, 422, 'Approve or reject all entries before locking the timesheet.');
        $previousStatus = $period->status;
        $period->update(['status' => 'locked', 'locked_by' => $request->user()->id, 'locked_at' => now()]);
        $this->recordAction($request, $period->member_id, 'locked', $previousStatus, 'locked', $period);
        $approvals->syncExternalDecision('timesheet_period', $period->id, 'approved', $request->attributes->get('workspaceMember'), 'Timesheet locked after entry review.');
        return response()->json(['data' => $period->fresh()]);
    }

    /** Handles the unlock period operation for the current WorkIntel workflow. */ public function unlockPeriod(Request $request, TimesheetPeriod $period): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($period->workspace_id === $workspace->id, 404);
        $this->assertCanManageMember($request->attributes->get('workspaceMember'), $period->member_id);
        $previousStatus = $period->status;
        $period->update(['status' => 'open', 'locked_by' => null, 'locked_at' => null]);
        $this->recordAction($request, $period->member_id, 'unlocked', $previousStatus, 'open', $period);
        return response()->json(['data' => $period->fresh()]);
    }

    /** Updates update approval data for the requested resource. */ public function updateApproval(Request $request, TimeEntry $entry): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($entry->workspace_id === $workspace->id, 404);
        $this->assertCanManageMember($request->attributes->get('workspaceMember'), $entry->member_id);
        $this->ensurePeriodEditable($workspace->id, $entry->member_id, $entry->date);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $previousStatus = $entry->approval_status;
        $entry->update(['approval_status' => $data['status']]);
        $period = $this->periodForEntry($entry);
        $this->recordAction($request, $entry->member_id, $data['status'], $previousStatus, $data['status'], $period, $entry, $data['note'] ?? null);
        return response()->json(['data' => $this->entryPayload($entry->fresh()->load(['member.user', 'project', 'task']))]);
    }

    /** Handles the bulk approve operation for the current WorkIntel workflow. */ public function bulkApprove(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate(['entry_ids' => ['required', 'array', 'min:1'], 'entry_ids.*' => ['integer']]);
        $entries = TimeEntry::query()->where('workspace_id', $workspace->id)->whereIn('id', $data['entry_ids'])->get();
        $updated = 0;
        foreach ($entries as $entry) {
            $this->assertCanManageMember($request->attributes->get('workspaceMember'), $entry->member_id);
            $this->ensurePeriodEditable($workspace->id, $entry->member_id, $entry->date);
            if (! in_array($entry->approval_status, ['draft', 'submitted'], true)) continue;
            $previousStatus = $entry->approval_status;
            $entry->update(['approval_status' => 'approved']);
            $this->recordAction($request, $entry->member_id, 'approved', $previousStatus, 'approved', $this->periodForEntry($entry), $entry, 'Bulk approval');
            $updated++;
        }
        return response()->json(['updated' => $updated]);
    }

    /** Validates validated manual entry input before it is processed. */ private function validatedManualEntry(Request $request): array
    {
        $workspace = $request->attributes->get('workspace');
        $currentMember = $request->attributes->get('workspaceMember');
        $data = $this->manualRules($request);
        $memberId = $this->canManageTime($currentMember) && ! empty($data['member_id']) ? (int) $data['member_id'] : $currentMember->id;
        $member = WorkspaceMember::query()->where('workspace_id', $workspace->id)->findOrFail($memberId);
        $this->assertCanEditMember($currentMember, $member->id);
        return [$workspace, $member, $data];
    }

    /** Handles the manual rules operation for the current WorkIntel workflow. */ private function manualRules(Request $request): array
    {
        return $request->validate([
            'member_id' => ['nullable', 'integer'], 'project_id' => ['nullable', 'integer'], 'task_id' => ['nullable', 'integer'],
            'started_at' => ['required', 'date'], 'ended_at' => ['required', 'date', 'after:started_at'],
            'billable' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** Handles the related work operation for the current WorkIntel workflow. */ private function relatedWork(WorkspaceMember $viewer, int $workspaceId, ?int $projectId, ?int $taskId): array
    {
        $task = $taskId ? Task::query()->where('workspace_id', $workspaceId)->findOrFail($taskId) : null;
        $project = $projectId ? Project::query()->where('workspace_id', $workspaceId)->findOrFail($projectId) : ($task?->project);
        if ($task && $project && $task->project_id !== $project->id) {
            throw ValidationException::withMessages(['task_id' => ['The selected task does not belong to the selected project.']]);
        }
        if ($task) abort_unless(app(WorkScopeService::class)->canViewTask($viewer, $task), 403, 'This task is outside your allowed work scope.');
        if ($project) abort_unless(app(WorkScopeService::class)->canViewProject($viewer, $project), 403, 'This project is outside your allowed work scope.');
        return [$project, $task];
    }

    /** Handles the ensure no overlap operation for the current WorkIntel workflow. */ private function ensureNoOverlap(int $workspaceId, int $memberId, Carbon $start, Carbon $end, ?int $ignoreId = null): void
    {
        $overlap = TimeEntry::query()->where('workspace_id', $workspaceId)->where('member_id', $memberId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('started_at', '<', $end)->where('ended_at', '>', $start)->exists();
        if ($overlap) throw ValidationException::withMessages(['started_at' => ['This time entry overlaps another entry.']]);
    }

    /** Handles the ensure period editable operation for the current WorkIntel workflow. */ private function ensurePeriodEditable(int $workspaceId, int $memberId, Carbon|string $date): void
    {
        $day = $date instanceof Carbon ? $date : Carbon::parse($date);
        $start = $day->copy()->startOfWeek()->toDateString();
        $locked = TimesheetPeriod::query()->where('workspace_id', $workspaceId)->where('member_id', $memberId)
            ->where('week_start', $start)->whereIn('status', ['locked', 'approved'])->exists();
        abort_if($locked, 422, 'This timesheet period is already finalized.');
    }

    /** Handles the period for entry operation for the current WorkIntel workflow. */ private function periodForEntry(TimeEntry $entry): TimesheetPeriod
    {
        return $this->periodForMemberWeek(
            $entry->workspace_id,
            $entry->member_id,
            $entry->date->copy()->startOfWeek(),
            'open'
        );
    }

    /** Handles the period for member week operation for the current WorkIntel workflow. */ private function periodForMemberWeek(int $workspaceId, int $memberId, Carbon $start, string $defaultStatus = 'open'): TimesheetPeriod
    {
        $weekStart = $start->copy()->startOfWeek()->toDateString();
        $period = TimesheetPeriod::query()
            ->where('workspace_id', $workspaceId)
            ->where('member_id', $memberId)
            ->where('week_start', $weekStart)
            ->first();

        if ($period) {
            return $period;
        }

        try {
            return TimesheetPeriod::create([
                'workspace_id' => $workspaceId,
                'member_id' => $memberId,
                'week_start' => $weekStart,
                'week_end' => $start->copy()->startOfWeek()->addDays(6)->toDateString(),
                'status' => $defaultStatus,
            ]);
        } catch (QueryException $e) {
            // Concurrent approvals/manual-time actions can race on the same
            // workspace/member/week. Resolve the row that won the unique key.
            $period = TimesheetPeriod::query()
                ->where('workspace_id', $workspaceId)
                ->where('member_id', $memberId)
                ->where('week_start', $weekStart)
                ->first();
            if ($period) return $period;
            throw $e;
        }
    }

    /** Handles the record action operation for the current WorkIntel workflow. */ private function recordAction(Request $request, int $memberId, string $action, ?string $previousStatus, ?string $newStatus, ?TimesheetPeriod $period = null, ?TimeEntry $entry = null, ?string $note = null): void
    {
        TimesheetAction::create([
            'workspace_id' => $request->attributes->get('workspace')->id,
            'timesheet_period_id' => $period?->id,
            'time_entry_id' => $entry?->id,
            'member_id' => $memberId,
            'actor_user_id' => $request->user()?->id,
            'action' => $action,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'metadata' => $entry ? ['date' => $entry->date->toDateString(), 'duration_seconds' => $entry->duration_seconds] : null,
            'created_at' => now(),
        ]);
    }

    /** @return array<int, int> */
    /** Handles the visible member ids operation for the current WorkIntel workflow. */ private function visibleMemberIds(WorkspaceMember $member): array
    {
        if ($member->hasPermission('time.view_all')) {
            return WorkspaceMember::query()->where('workspace_id', $member->workspace_id)->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        if ($member->hasPermission('time.view_team') || $member->hasPermission('time.manage')) {
            return app(WorkScopeService::class)->teamMemberIds($member);
        }
        return [(int) $member->id];
    }

    /** Handles the assert can edit member operation for the current WorkIntel workflow. */ private function assertCanEditMember(WorkspaceMember $actor, int $memberId): void
    {
        if ((int) $actor->id === $memberId) return;
        $this->assertCanManageMember($actor, $memberId);
    }

    /** Handles the assert can manage member operation for the current WorkIntel workflow. */ private function assertCanManageMember(WorkspaceMember $actor, int $memberId): void
    {
        abort_unless($this->canManageTime($actor), 403, 'Time management permission is required.');
        abort_unless(in_array($memberId, $this->visibleMemberIds($actor), true), 403, 'This employee is outside your timesheet scope.');
    }

    /** Determines whether the can manage time condition is satisfied. */ private function canManageTime(WorkspaceMember $member): bool { return $member->hasPermission('time.manage'); }

    /** Handles the summary operation for the current WorkIntel workflow. */ private function summary(Collection $entries): array
    {
        $tracked = (int) $entries->sum('duration_seconds');
        $billable = (int) $entries->where('billable', true)->sum('duration_seconds');
        return [
            'tracked_seconds' => $tracked, 'billable_seconds' => $billable,
            'non_billable_seconds' => max(0, $tracked - $billable),
            'pending_count' => $entries->whereIn('approval_status', ['draft', 'submitted'])->count(),
            'approved_count' => $entries->where('approval_status', 'approved')->count(),
        ];
    }

    /** Handles the entry payload operation for the current WorkIntel workflow. */ private function entryPayload(TimeEntry $entry): array
    {
        return [
            'id' => $entry->id, 'member_id' => $entry->member_id,
            'employee' => $entry->relationLoaded('member') && $entry->member?->user ? trim($entry->member->user->first_name.' '.$entry->member->user->last_name) : null,
            'project_id' => $entry->project_id, 'task_id' => $entry->task_id,
            'project' => $entry->project?->name, 'task' => $entry->task?->title,
            'date' => $entry->date->toDateString(), 'started_at' => $entry->started_at?->toIso8601String(), 'ended_at' => $entry->ended_at?->toIso8601String(),
            'duration_seconds' => $entry->duration_seconds, 'billable' => $entry->billable, 'source' => $entry->source,
            'approval_status' => $entry->approval_status, 'note' => $entry->note,
        ];
    }
}
