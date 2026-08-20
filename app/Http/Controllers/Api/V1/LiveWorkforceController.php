<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TimerStatus;
use App\Http\Controllers\Controller;
use App\Models\Screenshot;
use App\Models\TimeEntry;
use App\Models\TimeSession;
use App\Models\WorkerPresence;
use App\Models\WorkspaceMember;
use App\Services\WorkerPresenceService;
use App\Services\WorkerTimelineService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** Provides live workforce controller behavior within the WorkIntel application. */ class LiveWorkforceController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(
        private readonly WorkerPresenceService $presence,
        private readonly WorkerTimelineService $timeline,
    ) {}

    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['working', 'idle', 'break', 'meeting', 'offline'])],
            'department_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $memberIds = $this->visibleMemberIds($viewer);
        $members = WorkspaceMember::query()
            ->with(['user:id,first_name,last_name', 'department:id,name', 'jobTitle:id,name', 'teams:id,name'])
            ->where('workspace_id', $workspace->id)->where('status', 'active')->whereIn('id', $memberIds)
            ->when(! empty($data['department_id']), fn ($q) => $q->where('department_id', $data['department_id']))
            ->when(! empty($data['team_id']), fn ($q) => $q->whereHas('teams', fn ($team) => $team->whereKey($data['team_id'])))
            ->when(! empty($data['search']), function ($q) use ($data) {
                $term = '%'.trim($data['search']).'%';
                $q->whereHas('user', fn ($user) => $user->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term));
            })
            ->orderBy('id')->get();

        foreach ($members as $member) $this->presence->refresh($member);

        $presences = WorkerPresence::query()->with(['device:id,name,platform,last_sync_at,last_seen_at', 'project:id,name', 'task:id,title'])
            ->where('workspace_id', $workspace->id)->whereIn('member_id', $members->pluck('id'))
            ->when(! empty($data['status']), fn ($q) => $q->where('status', $data['status']))
            ->when(! empty($data['project_id']), fn ($q) => $q->where('project_id', $data['project_id']))
            ->get()->keyBy('member_id');

        $visibleMembers = $members->filter(fn ($member) => $presences->has($member->id));
        $today = now($workspace->timezone)->toDateString();
        $tracked = TimeEntry::query()->where('workspace_id', $workspace->id)->whereIn('member_id', $visibleMembers->pluck('id'))->where('date', $today)
            ->selectRaw('member_id, SUM(duration_seconds) as seconds')->groupBy('member_id')->pluck('seconds', 'member_id');
        $screenshots = Screenshot::query()->where('workspace_id', $workspace->id)->whereIn('member_id', $visibleMembers->pluck('id'))->whereNull('deleted_at')
            ->selectRaw('member_id, MAX(captured_at) as captured_at')->groupBy('member_id')->pluck('captured_at', 'member_id');
        $timers = TimeSession::query()->with('events:id,time_session_id,event_type,occurred_at')->where('workspace_id', $workspace->id)->whereIn('member_id', $visibleMembers->pluck('id'))
            ->whereIn('status', [TimerStatus::Running->value, TimerStatus::Paused->value])->get()->keyBy('member_id');

        $rows = $visibleMembers->map(function (WorkspaceMember $member) use ($presences, $tracked, $screenshots, $timers) {
            $p = $presences->get($member->id);
            $timer = $timers->get($member->id);
            return [
                'member_id' => $member->id,
                'name' => trim($member->user->first_name.' '.$member->user->last_name),
                'role' => $member->jobTitle?->name ?: $member->job_title,
                'department' => $member->department?->name,
                'teams' => $member->teams->pluck('name')->values(),
                'status' => $p->status,
                'status_since' => optional($p->status_since)->toIso8601String(),
                'project' => $p->project?->name,
                'project_id' => $p->project_id,
                'task' => $p->task?->title,
                'task_id' => $p->task_id,
                'timer_seconds' => $timer ? $this->timerSeconds($timer) : 0,
                'activity_percent' => $p->activity_percent,
                'app_name' => $p->app_name,
                'domain' => $p->domain,
                'tracking_status' => $p->tracking_status,
                'device' => $p->device?->name,
                'device_platform' => $p->device?->platform,
                'last_seen_at' => optional($p->last_seen_at)->toIso8601String(),
                'last_sync_at' => optional($p->device?->last_sync_at)->toIso8601String(),
                'last_screenshot_at' => isset($screenshots[$member->id]) ? CarbonImmutable::parse($screenshots[$member->id])->toIso8601String() : null,
                'tracked_today_seconds' => (int) ($tracked[$member->id] ?? 0) + ($timer ? $this->timerSeconds($timer) : 0),
                'attendance' => $p->metadata['attendance_status'] ?? null,
            ];
        })->values();

        $counts = collect(['working', 'idle', 'break', 'meeting', 'offline'])->mapWithKeys(fn ($status) => [$status => $rows->where('status', $status)->count()]);
        $revision = sha1($rows->map(fn ($row) => $row['member_id'].'|'.$row['status'].'|'.$row['last_seen_at'].'|'.$row['project_id'].'|'.$row['task_id'])->join(';'));

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'refresh_after_seconds' => max(2, (int) config('workintel.live.refresh_seconds', 5)),
            'revision' => $revision,
            'counts' => $counts,
            'data' => $rows,
        ]);
    }

    /** Handles the timeline operation for the current WorkIntel workflow. */ public function timeline(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        abort_unless((int) $member->workspace_id === (int) $workspace->id && in_array($member->id, $this->visibleMemberIds($viewer), true), 404);

        $data = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'groups' => ['nullable', 'string', 'max:180'],
        ]);
        $timezone = $workspace->timezone ?: config('app.timezone');
        $from = ! empty($data['from']) ? CarbonImmutable::parse($data['from'], $timezone)->startOfDay() : CarbonImmutable::now($timezone)->startOfDay();
        $to = ! empty($data['to']) ? CarbonImmutable::parse($data['to'], $timezone)->endOfDay() : CarbonImmutable::now($timezone)->endOfDay();
        abort_if($to->lessThan($from) || $from->diffInDays($to) > max(1, (int) config('workintel.live.timeline_max_days', 31)), 422, 'Timeline range is too large for this workspace.');
        $groups = array_values(array_filter(array_map('trim', explode(',', $data['groups'] ?? ''))));

        $member->load(['user:id,first_name,last_name', 'department:id,name', 'jobTitle:id,name']);
        $events = $this->timeline->forMember($workspace->id, $member->id, $from->utc(), $to->utc(), $groups)->take(500);

        return response()->json([
            'member' => ['id' => $member->id, 'name' => trim($member->user->first_name.' '.$member->user->last_name), 'role' => $member->jobTitle?->name ?: $member->job_title, 'department' => $member->department?->name],
            'from' => $from->toDateString(), 'to' => $to->toDateString(), 'events' => $events->values(),
        ]);
    }

    /** @return array<int, int> */
    /** Handles the visible member ids operation for the current WorkIntel workflow. */ private function visibleMemberIds(WorkspaceMember $viewer): array
    {
        if ($viewer->hasPermission('activity.view_all') || $viewer->hasPermission('activity.manage')) {
            return WorkspaceMember::query()->where('workspace_id', $viewer->workspace_id)->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        if ($viewer->hasPermission('activity.view_team')) {
            $teamIds = $viewer->teams()->pluck('teams.id');
            return WorkspaceMember::query()->where('workspace_id', $viewer->workspace_id)->where('status', 'active')->where(function ($q) use ($viewer, $teamIds) {
                $q->whereKey($viewer->id)->orWhere('manager_id', $viewer->id)->orWhereHas('teams', fn ($team) => $team->whereIn('teams.id', $teamIds));
            })->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        }
        abort_unless($viewer->hasPermission('activity.view_own'), 403, 'You do not have live workforce access.');
        return [(int) $viewer->id];
    }

    /** Handles the timer seconds operation for the current WorkIntel workflow. */ private function timerSeconds(TimeSession $session): int
    {
        $end = now(); $pausedAt = null; $paused = 0;
        foreach ($session->events->sortBy('occurred_at') as $event) {
            if ($event->event_type === 'timer.paused') $pausedAt = $event->occurred_at;
            elseif ($event->event_type === 'timer.resumed' && $pausedAt) { $paused += (int) $pausedAt->diffInSeconds($event->occurred_at); $pausedAt = null; }
        }
        if ($pausedAt) $paused += (int) $pausedAt->diffInSeconds($end);
        return max(0, (int) $session->started_at->diffInSeconds($end) - $paused);
    }
}
