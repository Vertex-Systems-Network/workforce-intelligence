<?php

namespace Tests\Feature;

use App\Models\FieldCheckpointVisit;
use App\Models\FieldWorkOrder;
use App\Models\MobileAccessToken;
use App\Models\MobileSyncEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides mobile field phase22 flow test behavior within the WorkIntel application. */ class MobileFieldFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test hash only mobile login checkpoint and offline sync are idempotent operation for the current WorkIntel workflow. */ public function test_hash_only_mobile_login_checkpoint_and_offline_sync_are_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $ownerMember = $owner->memberships()->with('workspace')->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $ownerMember->workspace_id];

        $checkpointResponse = $this->postJson('/api/v1/field/checkpoints', [
            'name' => 'Phase 22 Secure Checkpoint', 'type' => 'both',
        ], $headers)->assertCreated();
        $scanToken = $checkpointResponse->json('scan_token');
        $this->assertStringStartsWith('wifc_', $scanToken);
        $this->assertDatabaseMissing('field_checkpoints', ['scan_token_hash' => $scanToken]);

        $deviceUuid = (string) Str::uuid();
        $login = $this->postJson('/api/v1/mobile/login', [
            'email' => 'employee@acme.test', 'password' => 'password',
            'workspace' => 'acme-corp', 'device_uuid' => $deviceUuid,
            'platform' => 'android', 'device_name' => 'Phase 22 Test Device', 'app_version' => '1.0.0',
        ])->assertOk();
        $token = $login->json('access_token');
        $this->assertStringStartsWith('wim_', $token);
        $storedToken = MobileAccessToken::where('device_uuid', $deviceUuid)->firstOrFail();
        $this->assertSame(hash('sha256', $token), $storedToken->token_hash);
        $this->assertNotSame($token, $storedToken->token_hash);

        $mobileHeaders = ['Authorization' => 'Bearer '.$token];
        $bootstrap = $this->getJson('/api/v1/mobile/bootstrap', $mobileHeaders)->assertOk();
        $this->assertNotEmpty($bootstrap->json('work_orders'));
        $orderId = $bootstrap->json('work_orders.0.id');

        $this->postJson('/api/v1/mobile/checkpoints/scan', [
            'scan_token' => $scanToken, 'scan_method' => 'qr', 'field_work_order_id' => $orderId,
        ], $mobileHeaders)->assertCreated();
        $this->assertSame(1, FieldCheckpointVisit::where('field_work_order_id', $orderId)->where('member_id', $storedToken->member_id)->count());

        $eventUuid = (string) Str::uuid();
        $event = [
            'event_uuid' => $eventUuid,
            'event_type' => 'work_order.status',
            'occurred_at' => now()->toIso8601String(),
            'payload' => ['work_order_id' => $orderId, 'status' => 'in_progress', 'note' => 'Offline transition'],
        ];
        $first = $this->postJson('/api/v1/mobile/sync', ['events' => [$event]], $mobileHeaders)->assertOk();
        $this->assertSame('processed', $first->json('data.0.status'));
        $second = $this->postJson('/api/v1/mobile/sync', ['events' => [$event]], $mobileHeaders)->assertOk();
        $this->assertSame('duplicate', $second->json('data.0.status'));
        $this->assertSame(1, MobileSyncEvent::where('event_uuid', $eventUuid)->count());
        $this->assertSame('in_progress', FieldWorkOrder::findOrFail($orderId)->status);

        $this->postJson('/api/v1/mobile/logout', [], $mobileHeaders)->assertOk();
        $this->getJson('/api/v1/mobile/bootstrap', $mobileHeaders)->assertUnauthorized();
    }
}
