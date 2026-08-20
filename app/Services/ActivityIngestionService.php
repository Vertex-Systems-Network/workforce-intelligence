<?php

namespace App\Services;

use App\Models\ActivityTrackingSetting;
use App\Models\ApplicationSession;
use App\Models\BrowserConnection;
use App\Models\Device;
use App\Models\TrackingExclusion;
use App\Models\WebsiteSession;
use App\Models\WorkspaceMember;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides activity ingestion service behavior within the WorkIntel application. */ class ActivityIngestionService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly WorkEventService $events) {}
    /** Handles the ingest desktop event operation for the current WorkIntel workflow. */ public function ingestDesktopEvent(Device $device, array $event): bool
    {
        return match ($event['type']) {
            'app.session' => $this->storeApplicationSession($device->workspace_id, $device->member_id, $event['payload'] ?? [], $event['event_id'], $device->id, null, 'desktop_agent'),
            'website.session' => $this->storeWebsiteSession($device->workspace_id, $device->member_id, $event['payload'] ?? [], $event['event_id'], $device->id, null, 'desktop_agent'),
            default => false,
        };
    }

    /** Handles the ingest browser session operation for the current WorkIntel workflow. */ public function ingestBrowserSession(BrowserConnection $connection, array $session): bool
    {
        return $this->storeWebsiteSession(
            $connection->workspace_id,
            $connection->member_id,
            $session,
            $session['session_id'],
            $connection->device_id,
            $connection->id,
            'browser_extension'
        );
    }

    /** Handles the store application session operation for the current WorkIntel workflow. */ private function storeApplicationSession(int $workspaceId, int $memberId, array $payload, string $uuid, ?int $deviceId, ?int $browserConnectionId, string $source): bool
    {
        $settings = $this->settings($workspaceId);
        if (! $settings->application_tracking_enabled) return false;

        $appName = trim((string) ($payload['app_name'] ?? ''));
        $processName = trim((string) ($payload['process_name'] ?? ''));
        if ($processName !== '') $processName = basename(str_replace('\\', '/', $processName));
        if ($appName === '' && $processName === '') {
            throw ValidationException::withMessages(['events' => ['Application sessions require app_name or process_name.']]);
        }

        $appKey = Str::lower($processName !== '' ? $processName : $appName);
        if ($this->isExcluded($workspaceId, $memberId, 'app', $appKey)) return false;

        [$startedAt, $endedAt, $duration, $active, $idle] = $this->normalizeTimes($payload, $settings->minimum_session_seconds);
        if ($duration < $settings->minimum_session_seconds) return false;

        $projectId = $this->validProjectId($workspaceId, $payload['project_id'] ?? null);
        $taskId = $this->validTaskId($workspaceId, $projectId, $payload['task_id'] ?? null);

        $session = ApplicationSession::updateOrCreate(
            ['workspace_id' => $workspaceId, 'session_uuid' => $uuid],
            [
                'member_id' => $memberId,
                'device_id' => $deviceId,
                'project_id' => $projectId,
                'task_id' => $taskId,
                'app_key' => $appKey,
                'app_name' => mb_substr($appName !== '' ? $appName : $processName, 0, 180),
                'process_name' => $processName !== '' ? mb_substr($processName, 0, 180) : null,
                'window_title' => $settings->capture_window_titles && isset($payload['window_title']) ? mb_substr((string) $payload['window_title'], 0, 500) : null,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'duration_seconds' => $duration,
                'active_seconds' => $active,
                'idle_seconds' => $idle,
                'source' => $source,
            ]
        );

        $this->events->record([
            'workspace_id' => $workspaceId, 'member_id' => $memberId, 'device_id' => $deviceId,
            'project_id' => $projectId, 'task_id' => $taskId, 'event_type' => 'app.session', 'source' => $source,
            'title' => $session->app_name, 'detail' => $session->window_title, 'started_at' => $startedAt, 'ended_at' => $endedAt,
            'duration_seconds' => $duration, 'activity_percent' => $duration > 0 ? (int) round(($active / $duration) * 100) : null,
            'dedupe_key' => 'app:'.$uuid, 'metadata' => ['process_name' => $session->process_name],
        ]);

        return true;
    }

    /** Handles the store website session operation for the current WorkIntel workflow. */ private function storeWebsiteSession(int $workspaceId, int $memberId, array $payload, string $uuid, ?int $deviceId, ?int $browserConnectionId, string $source): bool
    {
        $settings = $this->settings($workspaceId);
        if (! $settings->website_tracking_enabled) return false;

        $domain = $this->normalizeDomain((string) ($payload['domain'] ?? ''));
        if ($domain === '') {
            throw ValidationException::withMessages(['sessions' => ['Website sessions require a valid domain.']]);
        }
        if ($this->isExcluded($workspaceId, $memberId, 'domain', $domain)) return false;

        [$startedAt, $endedAt, $duration, $active, $idle] = $this->normalizeTimes($payload, $settings->minimum_session_seconds);
        if ($duration < $settings->minimum_session_seconds) return false;

        $projectId = $this->validProjectId($workspaceId, $payload['project_id'] ?? null);
        $taskId = $this->validTaskId($workspaceId, $projectId, $payload['task_id'] ?? null);

        $session = WebsiteSession::updateOrCreate(
            ['workspace_id' => $workspaceId, 'session_uuid' => $uuid],
            [
                'member_id' => $memberId,
                'device_id' => $deviceId,
                'browser_connection_id' => $browserConnectionId,
                'project_id' => $projectId,
                'task_id' => $taskId,
                'domain' => $domain,
                'browser_name' => isset($payload['browser_name']) ? mb_substr((string) $payload['browser_name'], 0, 120) : null,
                'page_title' => $settings->capture_page_titles && isset($payload['page_title']) ? mb_substr((string) $payload['page_title'], 0, 500) : null,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'duration_seconds' => $duration,
                'active_seconds' => $active,
                'idle_seconds' => $idle,
                'source' => $source,
            ]
        );

        $this->events->record([
            'workspace_id' => $workspaceId, 'member_id' => $memberId, 'device_id' => $deviceId,
            'project_id' => $projectId, 'task_id' => $taskId, 'event_type' => 'website.session', 'source' => $source,
            'title' => $session->domain, 'detail' => $session->page_title, 'started_at' => $startedAt, 'ended_at' => $endedAt,
            'duration_seconds' => $duration, 'activity_percent' => $duration > 0 ? (int) round(($active / $duration) * 100) : null,
            'dedupe_key' => 'website:'.$uuid, 'metadata' => ['browser_name' => $session->browser_name],
        ]);

        return true;
    }

    /** Handles the normalize times operation for the current WorkIntel workflow. */ private function normalizeTimes(array $payload, int $minimumSeconds): array
    {
        try {
            $startedAt = CarbonImmutable::parse((string) ($payload['started_at'] ?? ''));
            $endedAt = CarbonImmutable::parse((string) ($payload['ended_at'] ?? ''));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['sessions' => ['started_at and ended_at must be valid dates.']]);
        }

        if ($endedAt->lessThanOrEqualTo($startedAt)) {
            throw ValidationException::withMessages(['sessions' => ['ended_at must be after started_at.']]);
        }

        $duration = (int) min(86400, max(0, $startedAt->diffInSeconds($endedAt)));
        $active = (int) min($duration, max(0, (int) ($payload['active_seconds'] ?? $duration)));
        $idle = (int) min($duration - $active, max(0, (int) ($payload['idle_seconds'] ?? ($duration - $active))));

        return [$startedAt, $endedAt, $duration, $active, $idle];
    }

    /** Handles the settings operation for the current WorkIntel workflow. */ private function settings(int $workspaceId): ActivityTrackingSetting
    {
        return ActivityTrackingSetting::firstOrCreate(['workspace_id' => $workspaceId]);
    }

    /** Handles the normalize domain operation for the current WorkIntel workflow. */ private function normalizeDomain(string $domain): string
    {
        $domain = trim(Str::lower($domain));
        if (str_contains($domain, '://')) {
            $domain = (string) parse_url($domain, PHP_URL_HOST);
        }
        $domain = preg_replace('/^www\./', '', $domain) ?? '';
        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ? mb_substr($domain, 0, 253) : '';
    }

    /** Determines whether the is excluded condition is satisfied. */ private function isExcluded(int $workspaceId, int $memberId, string $targetType, string $value): bool
    {
        $member = WorkspaceMember::with(['department', 'teams'])->find($memberId);
        $scopePairs = [['workspace', null], ['member', $memberId]];
        if ($member?->department_id) $scopePairs[] = ['department', $member->department_id];
        foreach ($member?->teams ?? [] as $team) $scopePairs[] = ['team', $team->id];

        return TrackingExclusion::query()
            ->where('workspace_id', $workspaceId)
            ->where('target_type', $targetType)
            ->where('active', true)
            ->get()
            ->contains(function (TrackingExclusion $rule) use ($scopePairs, $value) {
                $scopeMatches = collect($scopePairs)->contains(fn ($scope) => $rule->scope_type === $scope[0] && (int) ($rule->scope_id ?? 0) === (int) ($scope[1] ?? 0));
                if (! $scopeMatches) return false;
                $pattern = Str::lower(trim($rule->pattern));
                if ($rule->target_type === 'domain') {
                    return $value === $pattern || str_ends_with($value, '.'.$pattern);
                }
                return $value === $pattern || str_contains($value, $pattern);
            });
    }

    /** Handles the valid project id operation for the current WorkIntel workflow. */ private function validProjectId(int $workspaceId, mixed $projectId): ?int
    {
        if (! $projectId) return null;
        return \App\Models\Project::where('workspace_id', $workspaceId)->whereKey((int) $projectId)->value('id');
    }

    /** Handles the valid task id operation for the current WorkIntel workflow. */ private function validTaskId(int $workspaceId, ?int $projectId, mixed $taskId): ?int
    {
        if (! $taskId) return null;
        $query = \App\Models\Task::where('workspace_id', $workspaceId)->whereKey((int) $taskId);
        if ($projectId) $query->where('project_id', $projectId);
        return $query->value('id');
    }
}
