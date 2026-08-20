<?php

namespace Tests\Feature;

use App\Models\ApplicationSession;
use App\Models\BrowserConnection;
use App\Models\User;
use App\Models\WebsiteSession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides activity tracking flow test behavior within the WorkIntel application. */ class ActivityTrackingFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test owner can read usage manage rules and connect browser extension operation for the current WorkIntel workflow. */ public function test_owner_can_read_usage_manage_rules_and_connect_browser_extension(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $ownerMembership = $owner->memberships()->firstOrFail();
        $employeeMembership = User::where('email', 'employee@acme.test')->firstOrFail()->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $ownerMembership->workspace_id];

        $this->getJson('/api/v1/activity-tracking?from=2026-08-01&to=2026-08-31', $headers)
            ->assertOk()
            ->assertJsonPath('stats.applications', 3)
            ->assertJsonFragment(['name' => 'Visual Studio Code'])
            ->assertJsonFragment(['name' => 'github.com']);

        $rule = $this->postJson('/api/v1/activity-tracking/rules', [
            'scope_type' => 'member',
            'scope_id' => $employeeMembership->id,
            'target_type' => 'domain',
            'target' => 'docs.example.com',
            'classification' => 'productive',
            'category' => 'Documentation',
            'active' => true,
        ], $headers)->assertCreated();
        $this->assertDatabaseHas('productivity_rules', ['id' => $rule->json('data.id'), 'target' => 'docs.example.com']);

        $this->postJson('/api/v1/activity-tracking/exclusions', [
            'scope_type' => 'workspace',
            'target_type' => 'domain',
            'pattern' => 'private.example.com',
            'reason' => 'Private portal',
            'active' => true,
        ], $headers)->assertCreated();

        $code = $this->postJson('/api/v1/activity-tracking/browser-enrollments', [
            'member_id' => $employeeMembership->id,
            'expires_minutes' => 10,
        ], $headers)->assertCreated()->json('enrollment_code');

        $enrolled = $this->postJson('/api/v1/browser/enroll', [
            'enrollment_code' => $code,
            'installation_id' => 'test-chrome-extension',
            'browser_name' => 'Chrome',
            'browser_version' => '140',
            'extension_version' => '0.1.0',
        ])->assertCreated();

        $browserHeaders = ['Authorization' => 'Bearer '.$enrolled->json('access_token')];
        $sessionId = (string) Str::uuid();
        $this->postJson('/api/v1/browser/sync', [
            'sessions' => [[
                'session_id' => $sessionId,
                'domain' => 'docs.example.com',
                'browser_name' => 'Chrome',
                'started_at' => '2026-08-11T09:00:00+00:00',
                'ended_at' => '2026-08-11T09:10:00+00:00',
                'active_seconds' => 540,
                'idle_seconds' => 60,
            ]],
        ], $browserHeaders)->assertOk()->assertJsonPath('accepted', 1);

        $this->assertDatabaseHas('website_sessions', [
            'workspace_id' => $ownerMembership->workspace_id,
            'member_id' => $employeeMembership->id,
            'session_uuid' => $sessionId,
            'domain' => 'docs.example.com',
        ]);

        $connection = BrowserConnection::where('installation_id', 'test-chrome-extension')->firstOrFail();
        $this->postJson('/api/v1/activity-tracking/browser-connections/'.$connection->id.'/revoke', [], $headers)->assertOk();
        $this->postJson('/api/v1/browser/heartbeat', ['extension_version' => '0.1.0'], $browserHeaders)->assertUnauthorized();
    }

    /** Handles the test tracking ingestion rejects sensitive browser fields and excludes matching domains operation for the current WorkIntel workflow. */ public function test_tracking_ingestion_rejects_sensitive_browser_fields_and_excludes_matching_domains(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $ownerMembership = $owner->memberships()->firstOrFail();
        $employeeMembership = User::where('email', 'employee@acme.test')->firstOrFail()->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $ownerMembership->workspace_id];

        $code = $this->postJson('/api/v1/activity-tracking/browser-enrollments', ['member_id' => $employeeMembership->id], $headers)->assertCreated()->json('enrollment_code');
        $enrolled = $this->postJson('/api/v1/browser/enroll', [
            'enrollment_code' => $code,
            'installation_id' => 'privacy-browser',
            'browser_name' => 'Edge',
            'extension_version' => '0.1.0',
        ])->assertCreated();
        $browserHeaders = ['Authorization' => 'Bearer '.$enrolled->json('access_token')];

        $this->postJson('/api/v1/browser/sync', [
            'sessions' => [[
                'session_id' => (string) Str::uuid(),
                'domain' => 'github.com',
                'full_url' => 'https://github.com/acme/private/issues?token=secret',
                'started_at' => '2026-08-11T10:00:00+00:00',
                'ended_at' => '2026-08-11T10:05:00+00:00',
            ]],
        ], $browserHeaders)->assertUnprocessable();

        $before = WebsiteSession::where('domain', 'bank.example')->count();
        $this->postJson('/api/v1/browser/sync', [
            'sessions' => [[
                'session_id' => (string) Str::uuid(),
                'domain' => 'bank.example',
                'started_at' => '2026-08-11T10:00:00+00:00',
                'ended_at' => '2026-08-11T10:05:00+00:00',
            ]],
        ], $browserHeaders)->assertOk()->assertJsonPath('ignored', 1);
        $this->assertSame($before, WebsiteSession::where('domain', 'bank.example')->count());
    }

    /** Handles the test desktop agent app session is normalized and idempotent operation for the current WorkIntel workflow. */ public function test_desktop_agent_app_session_is_normalized_and_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $employee = User::where('email', 'employee@acme.test')->firstOrFail()->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $code = $this->postJson('/api/v1/devices/enrollments', ['member_id' => $employee->id], $headers)->assertCreated()->json('enrollment_code');
        $enrolled = $this->postJson('/api/v1/agent/enroll', [
            'enrollment_code' => $code,
            'installation_id' => 'activity-agent-device',
            'name' => 'Activity Agent',
            'platform' => 'windows',
            'os_name' => 'Windows 11',
            'agent_version' => '0.1.0',
            'capabilities' => ['heartbeat', 'offline_sync', 'app_tracking'],
        ])->assertCreated();
        $agentHeaders = ['Authorization' => 'Bearer '.$enrolled->json('access_token')];

        $eventId = (string) Str::uuid();
        $event = [
            'event_id' => $eventId,
            'type' => 'app.session',
            'occurred_at' => '2026-08-11T11:10:00+00:00',
            'payload' => [
                'app_name' => 'Visual Studio Code',
                'process_name' => 'C:\\Program Files\\Microsoft VS Code\\Code.exe',
                'window_title' => 'Sensitive project title should not persist by default',
                'started_at' => '2026-08-11T11:00:00+00:00',
                'ended_at' => '2026-08-11T11:10:00+00:00',
                'active_seconds' => 580,
                'idle_seconds' => 20,
            ],
        ];

        $this->postJson('/api/v1/agent/sync', [
            'batch_id' => (string) Str::uuid(),
            'events' => [$event],
        ], $agentHeaders)->assertOk()->assertJsonPath('accepted', 1);

        $this->assertDatabaseHas('application_sessions', [
            'workspace_id' => $membership->workspace_id,
            'member_id' => $employee->id,
            'session_uuid' => $eventId,
            'app_key' => 'code.exe',
            'process_name' => 'Code.exe',
            'window_title' => null,
        ]);

        $rawEvent = \App\Models\AgentEvent::where('event_uuid', $eventId)->firstOrFail();
        $this->assertArrayNotHasKey('window_title', $rawEvent->payload ?? []);

        $this->postJson('/api/v1/agent/sync', [
            'batch_id' => (string) Str::uuid(),
            'events' => [$event],
        ], $agentHeaders)->assertOk()->assertJsonPath('duplicates', 1);
        $this->assertSame(1, ApplicationSession::where('session_uuid', $eventId)->count());
    }
}
