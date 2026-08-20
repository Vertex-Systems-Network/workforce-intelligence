<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentCommand;
use App\Models\AgentEnrollment;
use App\Models\AgentEvent;
use App\Models\AgentSyncBatch;
use App\Models\Device;
use App\Models\DeviceAccessToken;
use App\Models\Workspace;
use App\Services\ActivityIngestionService;
use App\Services\Billing\EntitlementService;
use App\Services\WorkerPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Provides agent controller behavior within the WorkIntel application. */ class AgentController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(
        private readonly ActivityIngestionService $activityIngestion,
        private readonly WorkerPresenceService $presence,
    ) {}

    /** Handles the enroll operation for the current WorkIntel workflow. */ public function enroll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enrollment_code' => ['required', 'string', 'max:40'],
            'installation_id' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:160'],
            'platform' => ['required', Rule::in(['windows', 'macos', 'linux'])],
            'os_name' => ['required', 'string', 'max:80'],
            'os_version' => ['nullable', 'string', 'max:80'],
            'architecture' => ['nullable', 'string', 'max:32'],
            'agent_version' => ['required', 'string', 'max:32'],
            'machine_fingerprint' => ['nullable', 'string', 'max:500'],
            'capabilities' => ['nullable', 'array', 'max:30'],
            'capabilities.*' => ['string', 'max:80'],
        ]);

        $normalizedCode = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($data['enrollment_code'])) ?? '');
        $codeHash = hash('sha256', $normalizedCode);

        [$device, $plainToken] = DB::transaction(function () use ($request, $data, $codeHash) {
            $enrollment = AgentEnrollment::query()->where('code_hash', $codeHash)->lockForUpdate()->first();

            if (! $enrollment || $enrollment->used_at || $enrollment->expires_at->isPast()) {
                throw ValidationException::withMessages(['enrollment_code' => ['The enrollment code is invalid, expired, or already used.']]);
            }

            $device = Device::query()->firstOrNew([
                'workspace_id' => $enrollment->workspace_id,
                'installation_id' => $data['installation_id'],
            ]);

            $workspace = Workspace::findOrFail($enrollment->workspace_id);
            app(\App\Services\Modules\WorkspaceModuleService::class)->assertEnabled($workspace, 'devices');
            $entitlements = app(EntitlementService::class);
            $entitlements->assertFeature($workspace, 'feature.desktop_agent');
            if (! $device->exists) {
                $entitlements->assertWithinLimit($workspace, 'devices', $workspace->devices()->where('status', 'active')->count());
            }

            if (! $device->exists) {
                $device->uuid = (string) Str::uuid();
                $device->enrolled_at = now();
            }

            $device->fill([
                'member_id' => $enrollment->member_id,
                'name' => $data['name'],
                'platform' => $data['platform'],
                'os_name' => $data['os_name'],
                'os_version' => $data['os_version'] ?? null,
                'architecture' => $data['architecture'] ?? null,
                'agent_version' => $data['agent_version'],
                'machine_fingerprint_hash' => isset($data['machine_fingerprint']) ? hash('sha256', $data['machine_fingerprint']) : null,
                'status' => 'active',
                'tracking_status' => 'stopped',
                'is_idle' => false,
                'offline_queue_size' => 0,
                'capabilities' => array_values(array_unique($data['capabilities'] ?? [])),
                'last_ip' => $request->ip(),
                'last_seen_at' => now(),
                'revoked_at' => null,
            ])->save();

            $device->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $plainToken = 'wia_'.Str::random(64);
            DeviceAccessToken::create([
                'device_id' => $device->id,
                'name' => 'desktop-agent',
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addDays(config('workintel.agent.token_days', 365)),
                'created_at' => now(),
            ]);

            $enrollment->update(['used_at' => now()]);

            return [$device, $plainToken];
        });

        $this->presence->refreshByMemberId((int) $device->member_id);

        return response()->json([
            'device' => ['uuid' => $device->uuid, 'name' => $device->name, 'member_id' => $device->member_id],
            'access_token' => $plainToken,
            'config' => $this->agentConfig($device->workspace_id),
        ], 201);
    }

    /** Handles the heartbeat operation for the current WorkIntel workflow. */ public function heartbeat(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $data = $request->validate([
            'agent_version' => ['required', 'string', 'max:32'],
            'tracking_status' => ['required', Rule::in(['active', 'paused', 'stopped'])],
            'is_idle' => ['required', 'boolean'],
            'offline_queue_size' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'os_version' => ['nullable', 'string', 'max:80'],
            'capabilities' => ['nullable', 'array', 'max:30'],
            'capabilities.*' => ['string', 'max:80'],
            'metadata' => ['nullable', 'array'],
            'current_app' => ['nullable', 'string', 'max:180'],
            'current_domain' => ['nullable', 'string', 'max:253'],
            'current_project_id' => ['nullable', 'integer'],
            'current_task_id' => ['nullable', 'integer'],
            'activity_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
        $this->assertSafePayload($data['metadata'] ?? []);
        $currentProjectId = ! empty($data['current_project_id']) ? (int) $data['current_project_id'] : null;
        $currentTaskId = ! empty($data['current_task_id']) ? (int) $data['current_task_id'] : null;
        if ($currentProjectId) {
            abort_unless(\App\Models\Project::query()->where('workspace_id', $device->workspace_id)->whereKey($currentProjectId)->exists(), 422, 'Current project does not belong to this workspace.');
        }
        if ($currentTaskId) {
            $currentTask = \App\Models\Task::query()->where('workspace_id', $device->workspace_id)->whereKey($currentTaskId)->first();
            abort_unless($currentTask, 422, 'Current task does not belong to this workspace.');
            if ($currentProjectId) abort_unless((int) $currentTask->project_id === $currentProjectId, 422, 'Current task does not belong to the current project.');
            $currentProjectId ??= (int) $currentTask->project_id;
        }
        $currentDomain = isset($data['current_domain']) ? strtolower(trim($data['current_domain'])) : null;
        if ($currentDomain && str_contains($currentDomain, '://')) $currentDomain = (string) parse_url($currentDomain, PHP_URL_HOST);
        if ($currentDomain && ! filter_var($currentDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) abort(422, 'Current domain must be a hostname only.');
        $metadata = array_merge($data['metadata'] ?? [], array_filter([
            'current_app' => isset($data['current_app']) ? trim($data['current_app']) : null,
            'current_domain' => $currentDomain,
            'current_project_id' => $currentProjectId,
            'current_task_id' => $currentTaskId,
            'activity_percent' => $data['activity_percent'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        $device->update([
            'agent_version' => $data['agent_version'],
            'tracking_status' => $data['tracking_status'],
            'is_idle' => $data['is_idle'],
            'offline_queue_size' => $data['offline_queue_size'] ?? $device->offline_queue_size,
            'os_version' => $data['os_version'] ?? $device->os_version,
            'capabilities' => array_values(array_unique($data['capabilities'] ?? $device->capabilities ?? [])),
            'metadata' => $metadata ?: $device->metadata,
            'last_ip' => $request->ip(),
            'last_heartbeat_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->presence->refreshByMemberId((int) $device->member_id);

        $commands = $device->commands()->where('status', 'queued')->orderBy('id')->limit(20)->get();
        if ($commands->isNotEmpty()) {
            AgentCommand::query()->whereIn('id', $commands->pluck('id'))->update(['status' => 'delivered', 'delivered_at' => now()]);
        }

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'config' => $this->agentConfig($device->workspace_id),
            'commands' => $commands->map(fn (AgentCommand $command) => [
                'uuid' => $command->uuid,
                'command_type' => $command->command_type,
                'payload' => $command->payload,
            ])->values(),
        ]);
    }

    /** Synchronizes sync data with the current application state. */ public function sync(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $max = config('workintel.agent.sync_batch_max', 500);
        $data = $request->validate([
            'batch_id' => ['required', 'uuid'],
            'client_created_at' => ['nullable', 'date'],
            'events' => ['required', 'array', 'min:1', 'max:'.$max],
            'events.*.event_id' => ['required', 'uuid'],
            'events.*.type' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/'],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.payload' => ['nullable', 'array'],
        ]);

        $existingBatch = AgentSyncBatch::query()
            ->where('device_id', $device->id)
            ->where('batch_uuid', $data['batch_id'])
            ->first();

        if ($existingBatch) {
            return response()->json([
                'batch_id' => $existingBatch->batch_uuid,
                'accepted' => $existingBatch->accepted_count,
                'duplicates' => $existingBatch->duplicate_count,
                'replayed' => true,
                'server_time' => now()->toIso8601String(),
            ]);
        }

        $accepted = 0;
        $duplicates = 0;

        DB::transaction(function () use ($device, $data, &$accepted, &$duplicates) {
            foreach ($data['events'] as $event) {
                $this->assertSafePayload($event['payload'] ?? []);

                $exists = AgentEvent::query()->where('device_id', $device->id)->where('event_uuid', $event['event_id'])->exists();
                if ($exists) {
                    $duplicates++;
                    continue;
                }

                $diagnosticPayload = $event['payload'] ?? null;
                if (is_array($diagnosticPayload) && in_array($event['type'], ['app.session', 'website.session'], true)) {
                    // Titles are stored only in normalized session tables when the workspace explicitly enables them.
                    // Keeping them out of raw protocol logs prevents the diagnostic event stream from bypassing privacy settings.
                    unset($diagnosticPayload['window_title'], $diagnosticPayload['page_title']);
                }

                AgentEvent::create([
                    'event_uuid' => $event['event_id'],
                    'workspace_id' => $device->workspace_id,
                    'device_id' => $device->id,
                    'member_id' => $device->member_id,
                    'event_type' => $event['type'],
                    'occurred_at' => $event['occurred_at'],
                    'payload' => $diagnosticPayload,
                    'received_at' => now(),
                ]);

                // Known tracking events are normalized into query-friendly tables.
                // The raw event remains in agent_events for protocol diagnostics.
                $this->activityIngestion->ingestDesktopEvent($device, $event);
                $accepted++;
            }

            AgentSyncBatch::updateOrCreate(
                ['device_id' => $device->id, 'batch_uuid' => $data['batch_id']],
                [
                    'workspace_id' => $device->workspace_id,
                    'event_count' => count($data['events']),
                    'accepted_count' => $accepted,
                    'duplicate_count' => $duplicates,
                    'client_created_at' => $data['client_created_at'] ?? null,
                    'received_at' => now(),
                ]
            );

            $device->update([
                'last_sync_at' => now(),
                'last_seen_at' => now(),
                'last_ip' => request()->ip(),
                'offline_queue_size' => max(0, $device->offline_queue_size - count($data['events'])),
            ]);
        });

        $this->presence->refreshByMemberId((int) $device->member_id);

        return response()->json([
            'batch_id' => $data['batch_id'],
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** Handles the acknowledge command operation for the current WorkIntel workflow. */ public function acknowledgeCommand(Request $request, string $command): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $data = $request->validate([
            'status' => ['required', Rule::in(['acknowledged', 'failed'])],
            'result' => ['nullable', 'array'],
        ]);
        $this->assertSafePayload($data['result'] ?? []);

        $model = AgentCommand::query()->where('device_id', $device->id)->where('uuid', $command)->firstOrFail();
        abort_if($model->status === 'cancelled', 409, 'This command was cancelled.');

        $model->update([
            'status' => $data['status'],
            'acknowledged_at' => now(),
            'result' => $data['result'] ?? null,
        ]);

        if ($model->command_type === 'pause_tracking' && $data['status'] === 'acknowledged') {
            $device->update(['tracking_status' => 'paused']);
        }
        if ($model->command_type === 'resume_tracking' && $data['status'] === 'acknowledged') {
            $device->update(['tracking_status' => 'active']);
        }

        return response()->json(['message' => 'Command acknowledgement recorded.']);
    }

    /** Handles the agent config operation for the current WorkIntel workflow. */ private function agentConfig(?int $workspaceId = null): array
    {
        $settings = $workspaceId ? \App\Models\ActivityTrackingSetting::firstOrCreate(['workspace_id' => $workspaceId]) : null;
        $screenshotSettings = $workspaceId ? \App\Models\ScreenshotSetting::firstOrCreate(['workspace_id' => $workspaceId]) : null;
        return [
            'heartbeat_interval_seconds' => config('workintel.agent.heartbeat_interval_seconds', 30),
            'online_threshold_seconds' => config('workintel.agent.online_threshold_seconds', 90),
            'latest_version' => config('workintel.agent.latest_version', '0.1.0'),
            'minimum_supported_version' => config('workintel.agent.minimum_supported_version', '0.1.0'),
            'sync_batch_max' => config('workintel.agent.sync_batch_max', 500),
            'screenshots' => [
                'enabled' => $screenshotSettings?->enabled ?? false,
                'interval_minutes' => $screenshotSettings?->interval_minutes ?? 10,
                'randomize_minutes' => $screenshotSettings?->randomize_minutes ?? 2,
                'capture_all_monitors' => $screenshotSettings?->capture_all_monitors ?? false,
                'blur_by_default' => $screenshotSettings?->blur_by_default ?? false,
                'quality' => $screenshotSettings?->quality ?? 'medium',
                'max_upload_kb' => $screenshotSettings?->max_upload_kb ?? 4096,
                'capture_notification_mode' => $screenshotSettings?->capture_notification_mode ?? 'always',
                'notify_on_upload_failure' => $screenshotSettings?->notify_on_upload_failure ?? true,
            ],
            'activity' => [
                'application_tracking_enabled' => $settings?->application_tracking_enabled ?? true,
                'website_tracking_enabled' => $settings?->website_tracking_enabled ?? true,
                'capture_window_titles' => $settings?->capture_window_titles ?? false,
                'minimum_session_seconds' => $settings?->minimum_session_seconds ?? 5,
                'idle_threshold_seconds' => $settings?->idle_threshold_seconds ?? 300,
            ],
        ];
    }

    /** Handles the assert safe payload operation for the current WorkIntel workflow. */ private function assertSafePayload(array $payload): void
    {
        $forbidden = ['keystrokes', 'raw_keys', 'typed_text', 'clipboard_text', 'password', 'password_value', 'url', 'full_url', 'path', 'query', 'query_string', 'fragment', 'page_content', 'form_data'];
        $walk = function (array $value) use (&$walk, $forbidden): void {
            foreach ($value as $key => $item) {
                if (in_array(strtolower((string) $key), $forbidden, true)) {
                    throw ValidationException::withMessages(['events' => ['Raw keyboard, clipboard, or password content is not accepted by the agent API.']]);
                }
                if (is_array($item)) $walk($item);
            }
        };
        $walk($payload);
    }
}
