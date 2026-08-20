<?php

namespace App\Services;

use App\Models\ApplicationSession;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\Screenshot;
use App\Models\TimeSessionEvent;
use App\Models\WebsiteSession;
use App\Models\WorkEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Provides worker timeline service behavior within the WorkIntel application. */ class WorkerTimelineService
{
    /** Handles the for member operation for the current WorkIntel workflow. */ public function forMember(int $workspaceId, int $memberId, CarbonImmutable $from, CarbonImmutable $to, array $groups = []): Collection
    {
        $events = collect();

        $events->push(...WorkEvent::query()
            ->with(['project:id,name', 'task:id,title', 'device:id,name'])
            ->where('workspace_id', $workspaceId)
            ->where('member_id', $memberId)
            ->whereBetween('started_at', [$from, $to])
            ->get()
            ->map(fn (WorkEvent $event) => [
                'key' => 'work:'.$event->id,
                'type' => $event->event_type,
                'group' => $this->groupFor($event->event_type),
                'source' => $event->source,
                'title' => $event->title ?: $this->labelFor($event->event_type),
                'detail' => $event->detail,
                'started_at' => $event->started_at?->toIso8601String(),
                'ended_at' => $event->ended_at?->toIso8601String(),
                'duration_seconds' => $event->duration_seconds,
                'activity_percent' => $event->activity_percent,
                'project' => $event->project?->name,
                'task' => $event->task?->title,
                'device' => $event->device?->name,
                'metadata' => $event->metadata,
            ]));

        $events->push(...ApplicationSession::query()
            ->with(['project:id,name', 'task:id,title', 'device:id,name'])
            ->where('workspace_id', $workspaceId)->where('member_id', $memberId)
            ->whereBetween('started_at', [$from, $to])->get()->map(fn ($session) => [
                'key' => 'app:'.$session->id, 'type' => 'app.session', 'group' => 'applications', 'source' => $session->source,
                'title' => $session->app_name, 'detail' => $session->window_title,
                'started_at' => $session->started_at?->toIso8601String(), 'ended_at' => $session->ended_at?->toIso8601String(),
                'duration_seconds' => $session->duration_seconds,
                'activity_percent' => $session->duration_seconds > 0 ? (int) round(($session->active_seconds / $session->duration_seconds) * 100) : null,
                'project' => $session->project?->name, 'task' => $session->task?->title, 'device' => $session->device?->name,
                'metadata' => ['process_name' => $session->process_name],
            ]));

        $events->push(...WebsiteSession::query()
            ->with(['project:id,name', 'task:id,title', 'device:id,name'])
            ->where('workspace_id', $workspaceId)->where('member_id', $memberId)
            ->whereBetween('started_at', [$from, $to])->get()->map(fn ($session) => [
                'key' => 'site:'.$session->id, 'type' => 'website.session', 'group' => 'websites', 'source' => $session->source,
                'title' => $session->domain, 'detail' => $session->page_title,
                'started_at' => $session->started_at?->toIso8601String(), 'ended_at' => $session->ended_at?->toIso8601String(),
                'duration_seconds' => $session->duration_seconds,
                'activity_percent' => $session->duration_seconds > 0 ? (int) round(($session->active_seconds / $session->duration_seconds) * 100) : null,
                'project' => $session->project?->name, 'task' => $session->task?->title, 'device' => $session->device?->name,
                'metadata' => ['browser_name' => $session->browser_name],
            ]));

        $events->push(...TimeSessionEvent::query()
            ->with(['session.project:id,name', 'session.task:id,title'])
            ->whereHas('session', fn ($q) => $q->where('workspace_id', $workspaceId)->where('member_id', $memberId))
            ->whereBetween('occurred_at', [$from, $to])->get()->map(fn ($event) => [
                'key' => 'timer:'.$event->id, 'type' => $event->event_type, 'group' => 'tasks', 'source' => 'timer',
                'title' => $this->labelFor($event->event_type), 'detail' => null,
                'started_at' => $event->occurred_at?->toIso8601String(), 'ended_at' => null, 'duration_seconds' => null, 'activity_percent' => null,
                'project' => $event->session?->project?->name, 'task' => $event->session?->task?->title, 'device' => null, 'metadata' => null,
            ]));

        $records = AttendanceRecord::query()->where('workspace_id', $workspaceId)->where('member_id', $memberId)
            ->where(function ($q) use ($from, $to) { $q->whereBetween('clock_in_at', [$from, $to])->orWhereBetween('clock_out_at', [$from, $to]); })->get();
        foreach ($records as $record) {
            if ($record->clock_in_at && $record->clock_in_at->between($from, $to)) $events->push($this->simple('attendance:in:'.$record->id, 'attendance.clocked_in', 'attendance', 'Clocked in', $record->clock_in_at));
            if ($record->clock_out_at && $record->clock_out_at->between($from, $to)) $events->push($this->simple('attendance:out:'.$record->id, 'attendance.clocked_out', 'attendance', 'Clocked out', $record->clock_out_at));
        }

        $events->push(...AttendanceBreak::query()->where('workspace_id', $workspaceId)->where('member_id', $memberId)
            ->whereBetween('started_at', [$from, $to])->get()->flatMap(function ($break) use ($from, $to) {
                $rows = [$this->simple('break:start:'.$break->id, 'break.started', 'attendance', ucfirst($break->type).' break started', $break->started_at)];
                if ($break->ended_at && $break->ended_at->between($from, $to)) $rows[] = $this->simple('break:end:'.$break->id, 'break.ended', 'attendance', ucfirst($break->type).' break ended', $break->ended_at);
                return $rows;
            }));

        $events->push(...Screenshot::query()->with('device:id,name')->where('workspace_id', $workspaceId)->where('member_id', $memberId)
            ->whereNull('deleted_at')->whereBetween('captured_at', [$from, $to])->get()->map(fn ($shot) => [
                'key' => 'screenshot:'.$shot->id, 'type' => 'screenshot.captured', 'group' => 'screenshots', 'source' => 'desktop_agent',
                'title' => 'Screenshot captured', 'detail' => $shot->app_name,
                'started_at' => $shot->captured_at?->toIso8601String(), 'ended_at' => null, 'duration_seconds' => null, 'activity_percent' => $shot->activity_percent,
                'project' => null, 'task' => null, 'device' => $shot->device?->name, 'metadata' => ['screenshot_id' => $shot->id, 'blurred' => $shot->blurred],
            ]));

        return $events->unique('key')
            ->when($groups !== [], fn (Collection $items) => $items->filter(fn ($event) => in_array($event['group'], $groups, true)))
            ->sortByDesc('started_at')->values();
    }

    /** Handles the simple operation for the current WorkIntel workflow. */ private function simple(string $key, string $type, string $group, string $title, $date): array
    {
        return ['key' => $key, 'type' => $type, 'group' => $group, 'source' => 'attendance', 'title' => $title, 'detail' => null,
            'started_at' => $date?->toIso8601String(), 'ended_at' => null, 'duration_seconds' => null, 'activity_percent' => null,
            'project' => null, 'task' => null, 'device' => null, 'metadata' => null];
    }

    /** Handles the group for operation for the current WorkIntel workflow. */ private function groupFor(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'app.') => 'applications',
            str_starts_with($type, 'website.') => 'websites',
            str_starts_with($type, 'timer.'), str_starts_with($type, 'task.') => 'tasks',
            str_starts_with($type, 'attendance.'), str_starts_with($type, 'break.') => 'attendance',
            str_starts_with($type, 'screenshot.') => 'screenshots',
            str_starts_with($type, 'presence.') => 'presence',
            default => 'system',
        };
    }

    /** Handles the label for operation for the current WorkIntel workflow. */ private function labelFor(string $type): string
    {
        return match ($type) {
            'timer.started' => 'Timer started', 'timer.paused' => 'Timer paused', 'timer.resumed' => 'Timer resumed', 'timer.stopped' => 'Timer stopped',
            'presence.working' => 'Working', 'presence.idle' => 'Idle', 'presence.break' => 'On break', 'presence.meeting' => 'In meeting', 'presence.offline' => 'Offline',
            default => ucfirst(str_replace(['.', '_'], ' ', $type)),
        };
    }
}
