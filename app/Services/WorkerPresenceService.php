<?php

namespace App\Services;

use App\Enums\TimerStatus;
use App\Models\ApplicationSession;
use App\Models\BrowserConnection;
use App\Models\AttendanceRecord;
use App\Models\Device;
use App\Models\TimeSession;
use App\Models\WebsiteSession;
use App\Models\WorkerPresence;
use App\Models\WorkspaceMember;
use Carbon\CarbonImmutable;

/** Provides worker presence service behavior within the WorkIntel application. */ class WorkerPresenceService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly WorkEventService $events) {}

    /** Handles the refresh operation for the current WorkIntel workflow. */ public function refresh(WorkspaceMember $member): WorkerPresence
    {
        $member->loadMissing(['workspace', 'user']);
        $threshold = max(30, (int) config('workintel.agent.online_threshold_seconds', 90));
        $now = CarbonImmutable::now();
        $onlineSince = $now->subSeconds($threshold);

        $device = Device::query()
            ->where('workspace_id', $member->workspace_id)
            ->where('member_id', $member->id)
            ->whereNull('revoked_at')
            ->orderByDesc('last_seen_at')
            ->first();
        $deviceOnline = $device?->last_seen_at && $device->last_seen_at->gte($onlineSince);
        $browser = BrowserConnection::query()->where('workspace_id', $member->workspace_id)->where('member_id', $member->id)->where('status', 'active')->whereNull('revoked_at')->latest('last_seen_at')->first();
        $browserOnline = $browser?->last_seen_at && $browser->last_seen_at->gte($onlineSince);

        $timer = TimeSession::query()
            ->with(['project:id,name', 'task:id,title'])
            ->where('workspace_id', $member->workspace_id)
            ->where('member_id', $member->id)
            ->whereIn('status', [TimerStatus::Running->value, TimerStatus::Paused->value])
            ->latest('started_at')
            ->first();

        $timezone = $member->workspace?->timezone ?: config('app.timezone');
        $today = now($timezone)->toDateString();
        $attendance = AttendanceRecord::query()
            ->with(['breaks' => fn ($q) => $q->orderByDesc('started_at')])
            ->where('workspace_id', $member->workspace_id)
            ->where('member_id', $member->id)
            ->where('date', $today)
            ->first();
        $activeBreak = $attendance?->breaks?->firstWhere('ended_at', null);

        $latestApp = ApplicationSession::query()
            ->where('workspace_id', $member->workspace_id)
            ->where('member_id', $member->id)
            ->latest('ended_at')
            ->first();
        $latestWebsite = WebsiteSession::query()
            ->where('workspace_id', $member->workspace_id)
            ->where('member_id', $member->id)
            ->latest('ended_at')
            ->first();
        $contextCutoff = $now->subSeconds(max(300, $threshold * 2));
        if ($latestApp?->ended_at && $latestApp->ended_at->lt($contextCutoff)) $latestApp = null;
        if ($latestWebsite?->ended_at && $latestWebsite->ended_at->lt($contextCutoff)) $latestWebsite = null;

        $metadata = is_array($device?->metadata) ? $device->metadata : [];
        $appName = $this->cleanText($metadata['current_app'] ?? null, 180) ?: $latestApp?->app_name;
        $domain = $this->cleanDomain($metadata['current_domain'] ?? null) ?: $latestWebsite?->domain;
        $activity = isset($metadata['activity_percent']) ? max(0, min(100, (int) $metadata['activity_percent'])) : null;
        if ($activity === null && $latestApp && $latestApp->duration_seconds > 0) {
            $activity = (int) round(($latestApp->active_seconds / $latestApp->duration_seconds) * 100);
        }

        $status = 'offline';
        if ($activeBreak || $timer?->status === TimerStatus::Paused) {
            $status = 'break';
        } elseif ($this->looksLikeMeeting($appName, $domain) && ($deviceOnline || $browserOnline || $timer)) {
            $status = 'meeting';
        } elseif ($deviceOnline && $device?->is_idle) {
            $status = 'idle';
        } elseif ($timer?->status === TimerStatus::Running || ($deviceOnline && $device?->tracking_status === 'active') || $browserOnline) {
            $status = 'working';
        }

        $projectId = $timer?->project_id ?: (isset($metadata['current_project_id']) ? (int) $metadata['current_project_id'] : null);
        $taskId = $timer?->task_id ?: (isset($metadata['current_task_id']) ? (int) $metadata['current_task_id'] : null);

        $presence = WorkerPresence::firstOrNew([
            'workspace_id' => $member->workspace_id,
            'member_id' => $member->id,
        ]);
        $previousStatus = $presence->exists ? $presence->status : null;
        $statusSince = $previousStatus === $status && $presence->status_since ? $presence->status_since : $now;

        $presence->fill([
            'device_id' => $device?->id,
            'project_id' => $projectId ?: null,
            'task_id' => $taskId ?: null,
            'status' => $status,
            'tracking_status' => $device?->tracking_status,
            'app_name' => $appName,
            'domain' => $domain,
            'activity_percent' => $activity,
            'timer_started_at' => $timer?->started_at,
            'status_since' => $statusSince,
            'last_seen_at' => collect([$device?->last_seen_at, $browser?->last_seen_at, $timer ? $now : null])->filter()->sortByDesc(fn ($date) => $date->getTimestamp())->first() ?: $presence->last_seen_at,
            'metadata' => [
                'attendance_status' => $attendance?->status,
                'clocked_in' => (bool) ($attendance?->clock_in_at && ! $attendance?->clock_out_at),
                'active_break' => (bool) $activeBreak,
            ],
        ])->save();

        if ($previousStatus !== $status) {
            $this->events->record([
                'workspace_id' => $member->workspace_id,
                'member_id' => $member->id,
                'device_id' => $device?->id,
                'project_id' => $projectId ?: null,
                'task_id' => $taskId ?: null,
                'event_type' => 'presence.'.$status,
                'source' => 'presence',
                'title' => ucfirst($status),
                'started_at' => $now,
                'metadata' => ['previous_status' => $previousStatus],
            ]);
        }

        return $presence->fresh(['device', 'project', 'task']);
    }

    /** Handles the refresh by member id operation for the current WorkIntel workflow. */ public function refreshByMemberId(int $memberId): ?WorkerPresence
    {
        $member = WorkspaceMember::with(['workspace', 'user'])->find($memberId);
        return $member ? $this->refresh($member) : null;
    }

    /** Handles the looks like meeting operation for the current WorkIntel workflow. */ private function looksLikeMeeting(?string $appName, ?string $domain): bool
    {
        $haystack = strtolower(trim(($appName ?? '').' '.($domain ?? '')));
        foreach (['zoom', 'microsoft teams', 'teams.exe', 'meet.google.com', 'google meet', 'slack huddle'] as $needle) {
            if (str_contains($haystack, $needle)) return true;
        }
        return false;
    }

    /** Handles the clean text operation for the current WorkIntel workflow. */ private function cleanText(mixed $value, int $max): ?string
    {
        if (! is_string($value)) return null;
        $value = trim($value);
        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /** Handles the clean domain operation for the current WorkIntel workflow. */ private function cleanDomain(mixed $value): ?string
    {
        if (! is_string($value)) return null;
        $value = strtolower(trim($value));
        if (str_contains($value, '://')) $value = (string) parse_url($value, PHP_URL_HOST);
        $value = preg_replace('/^www\./', '', $value) ?? '';
        return filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ? mb_substr($value, 0, 253) : null;
    }
}
