<?php

namespace Tests\Feature;

use App\Models\AutomationEvent;
use App\Models\AutomationIncomingHook;
use App\Models\AutomationRun;
use App\Models\AutomationWorkflow;
use App\Models\User;
use App\Models\WorkspaceNotification;
use App\Services\Automation\AutomationEngine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides automation phase24 flow test behavior within the WorkIntel application. */ class AutomationFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test incoming hook is hash only idempotent and runs notification workflow operation for the current WorkIntel workflow. */ public function test_incoming_hook_is_hash_only_idempotent_and_runs_notification_workflow(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $workspaceId = $membership->workspace_id;
        $headers = ['X-Workspace-Id' => (string) $workspaceId];
        Sanctum::actingAs($owner);

        $workflow = $this->postJson('/api/v1/automations', [
            'name' => 'External ticket notification',
            'description' => 'Phase 24 incoming hook flow test.',
            'status' => 'active',
            'trigger_type' => 'incoming',
            'trigger_event' => 'external.ticket',
            'conditions' => [],
            'condition_mode' => 'all',
            'failure_policy' => 'stop',
            'max_run_seconds' => 30,
            'actions' => [[
                'name' => 'Notify owners',
                'action_type' => 'notification',
                'action_key' => 'notify',
                'config' => [
                    'role_slugs' => ['owner', 'admin'],
                    'title' => 'Incoming {{payload.ticket}}',
                    'body' => 'Received {{event.type}}',
                    'severity' => 'info',
                ],
                'continue_on_error' => false,
                'retry_max' => 1,
                'timeout_seconds' => 8,
            ]],
        ], $headers)->assertCreated()->json('data');

        $hook = $this->postJson('/api/v1/automation-incoming-hooks', [
            'name' => 'Ticket intake',
            'event_name' => 'external.ticket',
            'workflow_id' => $workflow['id'],
            'rate_limit_per_minute' => 60,
        ], $headers)->assertCreated();
        $raw = $hook->json('token');
        $uuid = $hook->json('data.uuid');
        $this->assertStringStartsWith('wiin_', $raw);
        $row = AutomationIncomingHook::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(hash('sha256', $raw), $row->token_hash);
        $this->assertNotSame($raw, $row->token_hash);

        $publicHeaders = [
            'Authorization' => 'Bearer '.$raw,
            'X-WorkIntel-Idempotency-Key' => 'ticket-123',
        ];
        $first = $this->postJson('/api/incoming/v1/automations/'.$uuid, ['ticket' => 'INC-123'], $publicHeaders)
            ->assertAccepted();
        $eventId = $first->json('event_id');
        $this->assertNotEmpty($eventId);
        $this->assertDatabaseCount('automation_events', AutomationEvent::count());
        $eventCount = AutomationEvent::where('workspace_id', $workspaceId)->where('source', 'incoming:'.$uuid)->count();
        $runCount = AutomationRun::where('workspace_id', $workspaceId)->where('automation_workflow_id', $workflow['id'])->count();
        $this->assertSame(1, $eventCount);
        $this->assertSame(1, $runCount);

        $this->postJson('/api/incoming/v1/automations/'.$uuid, ['ticket' => 'INC-123'], $publicHeaders)
            ->assertAccepted()->assertJsonPath('event_id', $eventId);
        $this->assertSame(1, AutomationEvent::where('workspace_id', $workspaceId)->where('source', 'incoming:'.$uuid)->count());
        $this->assertSame(1, AutomationRun::where('workspace_id', $workspaceId)->where('automation_workflow_id', $workflow['id'])->count());

        $this->artisan('workintel:automation-maintenance', ['--limit' => 50])->assertSuccessful();
        $this->assertSame('succeeded', AutomationRun::where('automation_workflow_id', $workflow['id'])->latest('id')->firstOrFail()->status);
        $this->assertTrue(WorkspaceNotification::where('workspace_id', $workspaceId)->where('type', 'automation.notification')->exists());
    }

    /** Handles the test non admin system roles do not receive automation log access by default operation for the current WorkIntel workflow. */ public function test_non_admin_system_roles_do_not_receive_automation_log_access_by_default(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = User::where('email', 'manager@acme.test')->firstOrFail();
        $membership = $manager->memberships()->firstOrFail();
        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/automations/overview', ['X-Workspace-Id' => (string) $membership->workspace_id])
            ->assertForbidden();
    }

    /** Handles the test conditions and direct webhook ssrf validation are enforced operation for the current WorkIntel workflow. */ public function test_conditions_and_direct_webhook_ssrf_validation_are_enforced(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $workspace = $membership->workspace;
        $headers = ['X-Workspace-Id' => (string) $workspace->id];
        Sanctum::actingAs($owner);

        $workflow = $this->postJson('/api/v1/automations', [
            'name' => 'Critical only',
            'status' => 'active',
            'trigger_type' => 'event',
            'trigger_event' => 'custom.alert',
            'conditions' => [['field' => 'payload.level', 'operator' => 'eq', 'value' => 'critical']],
            'condition_mode' => 'all',
            'failure_policy' => 'stop',
            'actions' => [[
                'name' => 'Notify', 'action_type' => 'notification', 'action_key' => 'notify',
                'config' => ['role_slugs' => ['owner'], 'title' => 'Critical alert'],
            ]],
        ], $headers)->assertCreated()->json('data');

        app(AutomationEngine::class)->emit($workspace, 'custom.alert', ['level' => 'info'], 'test');
        $this->assertSame(0, AutomationRun::where('automation_workflow_id', $workflow['id'])->count());
        app(AutomationEngine::class)->emit($workspace, 'custom.alert', ['level' => 'critical'], 'test');
        $this->assertSame(1, AutomationRun::where('automation_workflow_id', $workflow['id'])->count());

        $this->postJson('/api/v1/automations', [
            'name' => 'Unsafe private webhook', 'status' => 'draft', 'trigger_type' => 'event',
            'trigger_event' => 'custom.unsafe', 'conditions' => [],
            'actions' => [[
                'name' => 'Private HTTP', 'action_type' => 'webhook', 'action_key' => 'http.post',
                'config' => ['url' => 'http://127.0.0.1/internal', 'body' => ['hello' => 'world']],
            ]],
        ], $headers)->assertUnprocessable();
    }

    /** Handles the test overview exposes connector catalog and workflow actions for safe editing operation for the current WorkIntel workflow. */ public function test_overview_exposes_connector_catalog_and_workflow_actions_for_safe_editing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $overview = $this->getJson('/api/v1/automations/overview', $headers)->assertOk();
        $this->assertGreaterThanOrEqual(13, count($overview->json('connectors')));
        $workflow = collect($overview->json('workflows'))->firstWhere('name', 'Payroll Paid · Admin Notification');
        $this->assertNotNull($workflow);
        $this->assertNotEmpty($workflow['actions']);
    }
}
