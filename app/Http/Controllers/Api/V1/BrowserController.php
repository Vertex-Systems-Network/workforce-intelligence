<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BrowserAccessToken;
use App\Models\BrowserConnection;
use App\Models\BrowserEnrollment;
use App\Models\AgentEnrollment;
use App\Models\Workspace;
use App\Services\ActivityIngestionService;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides browser controller behavior within the WorkIntel application. */ class BrowserController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly ActivityIngestionService $ingestion) {}

    /** Handles the enroll operation for the current WorkIntel workflow. */ public function enroll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enrollment_code' => ['required', 'string', 'max:40'],
            'installation_id' => ['required', 'string', 'max:120'],
            'browser_name' => ['required', 'string', 'max:80'],
            'browser_version' => ['nullable', 'string', 'max:40'],
            'extension_version' => ['required', 'string', 'max:40'],
            'device_uuid' => ['nullable', 'uuid'],
        ]);

        $normalizedCode = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($data['enrollment_code'])) ?? '');
        $codeHash = hash('sha256', $normalizedCode);

        [$connection, $plainToken] = DB::transaction(function () use ($request, $data, $codeHash) {
            $browserEnrollment = BrowserEnrollment::query()->where('code_hash', $codeHash)->lockForUpdate()->first();
            $agentEnrollment = $browserEnrollment ? null : AgentEnrollment::query()->where('code_hash', $codeHash)->lockForUpdate()->first();

            $enrollment = $browserEnrollment ?? $agentEnrollment;
            if ($enrollment) { $workspace = Workspace::findOrFail($enrollment->workspace_id); app(\App\Services\Modules\WorkspaceModuleService::class)->assertEnabled($workspace, 'activity'); app(EntitlementService::class)->assertFeature($workspace, 'feature.browser_tracking'); }

            $alreadyUsed = $browserEnrollment ? (bool) $browserEnrollment->used_at : (bool) $agentEnrollment?->browser_used_at;
            if (! $enrollment || $alreadyUsed || $enrollment->expires_at->isPast()) {
                throw ValidationException::withMessages(['enrollment_code' => ['The enrollment code is invalid, expired, or already used for this browser. Generate a fresh code from Devices & Agents or Apps & Websites.']]);
            }

            $deviceId = null;
            if (! empty($data['device_uuid'])) {
                $deviceId = \App\Models\Device::query()
                    ->where('workspace_id', $enrollment->workspace_id)
                    ->where('member_id', $enrollment->member_id)
                    ->where('uuid', $data['device_uuid'])
                    ->value('id');
            }

            $connection = BrowserConnection::query()->firstOrNew([
                'workspace_id' => $enrollment->workspace_id,
                'installation_id' => $data['installation_id'],
            ]);
            if (! $connection->exists) {
                $connection->uuid = (string) Str::uuid();
                $connection->enrolled_at = now();
            }
            $connection->fill([
                'member_id' => $enrollment->member_id,
                'device_id' => $deviceId,
                'browser_name' => $data['browser_name'],
                'browser_version' => $data['browser_version'] ?? null,
                'extension_version' => $data['extension_version'],
                'status' => 'active',
                'last_seen_at' => now(),
                'last_ip' => $request->ip(),
                'revoked_at' => null,
            ])->save();

            $connection->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $plainToken = 'wib_'.Str::random(64);
            BrowserAccessToken::create([
                'browser_connection_id' => $connection->id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addDays(config('workintel.browser.token_days', 365)),
                'created_at' => now(),
            ]);
            if ($browserEnrollment) $browserEnrollment->update(['used_at' => now()]);
            else $agentEnrollment->update(['browser_used_at' => now()]);

            return [$connection, $plainToken];
        });

        return response()->json([
            'connection' => ['uuid' => $connection->uuid, 'browser_name' => $connection->browser_name, 'member_id' => $connection->member_id],
            'access_token' => $plainToken,
            'config' => $this->config($connection->workspace_id),
        ], 201);
    }

    /** Handles the heartbeat operation for the current WorkIntel workflow. */ public function heartbeat(Request $request): JsonResponse
    {
        /** @var BrowserConnection $connection */
        $connection = $request->attributes->get('browserConnection');
        $data = $request->validate([
            'browser_version' => ['nullable', 'string', 'max:40'],
            'extension_version' => ['required', 'string', 'max:40'],
        ]);
        $connection->update([
            'browser_version' => $data['browser_version'] ?? $connection->browser_version,
            'extension_version' => $data['extension_version'],
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
        ]);

        return response()->json(['server_time' => now()->toIso8601String(), 'config' => $this->config($connection->workspace_id)]);
    }

    /** Synchronizes sync data with the current application state. */ public function sync(Request $request): JsonResponse
    {
        /** @var BrowserConnection $connection */
        $connection = $request->attributes->get('browserConnection');
        app(EntitlementService::class)->assertFeature(Workspace::findOrFail($connection->workspace_id), 'feature.browser_tracking');
        $max = config('workintel.browser.sync_batch_max', 250);
        $data = $request->validate([
            'sessions' => ['required', 'array', 'min:1', 'max:'.$max],
            'sessions.*.session_id' => ['required', 'uuid'],
            'sessions.*.domain' => ['required', 'string', 'max:500'],
            'sessions.*.browser_name' => ['nullable', 'string', 'max:120'],
            'sessions.*.started_at' => ['required', 'date'],
            'sessions.*.ended_at' => ['required', 'date'],
            'sessions.*.active_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'sessions.*.idle_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'sessions.*.project_id' => ['nullable', 'integer'],
            'sessions.*.task_id' => ['nullable', 'integer'],
            'sessions.*.page_title' => ['nullable', 'string', 'max:500'],
        ]);
        $this->assertNoSensitiveBrowserFields($request->all());

        $accepted = 0;
        $ignored = 0;
        foreach ($data['sessions'] as $session) {
            if ($this->ingestion->ingestBrowserSession($connection, $session)) $accepted++; else $ignored++;
        }

        $connection->update(['last_seen_at' => now(), 'last_sync_at' => now(), 'last_ip' => $request->ip()]);

        return response()->json([
            'accepted' => $accepted,
            'ignored' => $ignored,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** Handles the assert no sensitive browser fields operation for the current WorkIntel workflow. */ private function assertNoSensitiveBrowserFields(array $payload): void
    {
        $forbidden = ['url', 'full_url', 'path', 'query', 'query_string', 'fragment', 'page_content', 'form_data', 'clipboard', 'typed_text', 'keystrokes', 'password'];
        $walk = function (array $value) use (&$walk, $forbidden): void {
            foreach ($value as $key => $nested) {
                if (is_string($key) && in_array(strtolower($key), $forbidden, true)) {
                    throw ValidationException::withMessages(['sessions' => ["Sensitive browser field '{$key}' is not accepted. Send domain-only metadata."]]);
                }
                if (is_array($nested)) $walk($nested);
            }
        };
        $walk($payload);
    }

    /** Handles the config operation for the current WorkIntel workflow. */ private function config(?int $workspaceId = null): array
    {
        $settings = $workspaceId ? \App\Models\ActivityTrackingSetting::firstOrCreate(['workspace_id' => $workspaceId]) : null;
        return [
            'heartbeat_interval_seconds' => config('workintel.browser.heartbeat_interval_seconds', 60),
            'sync_interval_seconds' => config('workintel.browser.sync_interval_seconds', 60),
            'domain_only' => true,
            'website_tracking_enabled' => $settings?->website_tracking_enabled ?? true,
            'minimum_session_seconds' => $settings?->minimum_session_seconds ?? 5,
            'idle_threshold_seconds' => $settings?->idle_threshold_seconds ?? 300,
        ];
    }
}
