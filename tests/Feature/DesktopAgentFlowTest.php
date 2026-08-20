<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides desktop agent flow test behavior within the WorkIntel application. */ class DesktopAgentFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test device can enroll heartbeat sync offline events and receive commands operation for the current WorkIntel workflow. */ public function test_device_can_enroll_heartbeat_sync_offline_events_and_receive_commands(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $employee = User::where('email', 'employee@acme.test')->firstOrFail()->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $enrollment = $this->postJson('/api/v1/devices/enrollments', [
            'member_id' => $employee->id,
            'expires_minutes' => 10,
        ], $headers)->assertCreated();

        $enrolled = $this->postJson('/api/v1/agent/enroll', [
            'enrollment_code' => $enrollment->json('enrollment_code'),
            'installation_id' => 'test-device-installation',
            'name' => 'TEST-LAPTOP',
            'platform' => 'windows',
            'os_name' => 'Windows 11',
            'os_version' => '24H2',
            'architecture' => 'x64',
            'agent_version' => '0.1.0',
            'capabilities' => ['heartbeat', 'offline_sync', 'commands'],
        ])->assertCreated();

        $token = $enrolled->json('access_token');
        $this->assertIsString($token);
        $this->assertStringStartsWith('wia_', $token);
        $agentHeaders = ['Authorization' => 'Bearer '.$token];

        $this->postJson('/api/v1/agent/heartbeat', [
            'agent_version' => '0.1.0',
            'tracking_status' => 'active',
            'is_idle' => false,
            'offline_queue_size' => 2,
            'capabilities' => ['heartbeat', 'offline_sync', 'commands'],
        ], $agentHeaders)->assertOk()->assertJsonPath('config.heartbeat_interval_seconds', 30);

        $eventId = (string) Str::uuid();
        $batchId = (string) Str::uuid();
        $this->postJson('/api/v1/agent/sync', [
            'batch_id' => $batchId,
            'client_created_at' => now()->toIso8601String(),
            'events' => [[
                'event_id' => $eventId,
                'type' => 'connectivity.online',
                'occurred_at' => now()->subMinute()->toIso8601String(),
                'payload' => ['source' => 'offline-queue'],
            ]],
        ], $agentHeaders)->assertOk()->assertJsonPath('accepted', 1);

        $this->assertDatabaseHas('agent_events', ['event_uuid' => $eventId, 'member_id' => $employee->id]);

        $device = Device::where('installation_id', 'test-device-installation')->firstOrFail();
        $command = $this->postJson('/api/v1/devices/'.$device->id.'/commands', [
            'command_type' => 'pause_tracking',
        ], $headers)->assertCreated();

        $heartbeat = $this->postJson('/api/v1/agent/heartbeat', [
            'agent_version' => '0.1.0',
            'tracking_status' => 'active',
            'is_idle' => false,
            'offline_queue_size' => 0,
        ], $agentHeaders)->assertOk();

        $this->assertSame($command->json('command.uuid'), $heartbeat->json('commands.0.uuid'));

        $this->postJson('/api/v1/agent/commands/'.$command->json('command.uuid').'/ack', [
            'status' => 'acknowledged',
            'result' => ['tracking_status' => 'paused'],
        ], $agentHeaders)->assertOk();

        $this->assertSame('paused', $device->fresh()->tracking_status);
    }

    /** Handles the test agent api rejects raw keyboard content and revoked tokens operation for the current WorkIntel workflow. */ public function test_agent_api_rejects_raw_keyboard_content_and_revoked_tokens(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $member = $owner->memberships()->firstOrFail();
        $code = $this->postJson('/api/v1/devices/enrollments', ['member_id' => $member->id], $headers)->assertCreated()->json('enrollment_code');
        $enrolled = $this->postJson('/api/v1/agent/enroll', [
            'enrollment_code' => $code,
            'installation_id' => 'privacy-test-device',
            'name' => 'Privacy Test',
            'platform' => 'linux',
            'os_name' => 'Linux',
            'agent_version' => '0.1.0',
        ])->assertCreated();
        $agentHeaders = ['Authorization' => 'Bearer '.$enrolled->json('access_token')];

        $this->postJson('/api/v1/agent/sync', [
            'batch_id' => (string) Str::uuid(),
            'events' => [[
                'event_id' => (string) Str::uuid(),
                'type' => 'activity.sample',
                'occurred_at' => now()->toIso8601String(),
                'payload' => ['typed_text' => 'secret'],
            ]],
        ], $agentHeaders)->assertUnprocessable();

        $device = Device::where('installation_id', 'privacy-test-device')->firstOrFail();
        $this->postJson('/api/v1/devices/'.$device->id.'/revoke', [], $headers)->assertOk();

        $this->postJson('/api/v1/agent/heartbeat', [
            'agent_version' => '0.1.0', 'tracking_status' => 'stopped', 'is_idle' => false,
        ], $agentHeaders)->assertUnauthorized();
    }
}
