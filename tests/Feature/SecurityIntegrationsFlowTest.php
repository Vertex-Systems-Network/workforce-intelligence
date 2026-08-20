<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\WebhookDelivery;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides security integrations flow test behavior within the WorkIntel application. */ class SecurityIntegrationsFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test api keys notifications webhooks and audit are workspace scoped operation for the current WorkIntel workflow. */ public function test_api_keys_notifications_webhooks_and_audit_are_workspace_scoped(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner=User::where('email','owner@acme.test')->firstOrFail();
        $membership=$owner->memberships()->firstOrFail();
        $headers=['X-Workspace-Id'=>(string)$membership->workspace_id];
        Sanctum::actingAs($owner);

        $overview=$this->getJson('/api/v1/security-integrations',$headers)->assertOk();
        $this->assertContains('people.read',$overview->json('api_scope_catalog'));

        $keyResponse=$this->postJson('/api/v1/api-keys',[
            'name'=>'Test read API','scopes'=>['people.read','projects.read'],'expires_days'=>30,
        ],$headers)->assertCreated();
        $token=$keyResponse->json('token');
        $this->assertStringStartsWith('wiax_',$token);
        $apiHeaders=['Authorization'=>'Bearer '.$token];

        $this->getJson('/api/public/v1/me',$apiHeaders)->assertOk()->assertJsonPath('workspace.id',$membership->workspace_id);
        $this->getJson('/api/public/v1/people',$apiHeaders)->assertOk();
        $this->postJson('/api/public/v1/time-entries',[], $apiHeaders)->assertForbidden();

        $prefs=$this->getJson('/api/v1/notification-preferences',$headers)->assertOk()->json('data');
        $this->assertNotEmpty($prefs);
        $this->putJson('/api/v1/notification-preferences',['preferences'=>array_map(fn($p)=>['category'=>$p['category'],'in_app'=>true,'email'=>false,'digest'=>'immediate'],$prefs)],$headers)->assertOk();

        $hook=$this->postJson('/api/v1/webhooks',[
            'name'=>'Test Hook','url'=>'https://example.com/workintel-hook','events'=>['*'],'max_attempts'=>3,
        ],$headers)->assertCreated();
        $this->assertStringStartsWith('whsec_',$hook->json('signing_secret'));

        $this->postJson('/api/v1/api-keys',[
            'name'=>'Second Key','scopes'=>['tasks.read'],'expires_days'=>10,
        ],$headers)->assertCreated();

        $this->assertGreaterThan(0,AuditLog::where('workspace_id',$membership->workspace_id)->count());
        $this->assertGreaterThan(0,WebhookDelivery::where('workspace_id',$membership->workspace_id)->count());
        $audit=$this->getJson('/api/v1/audit-logs',$headers)->assertOk()->json('data');
        $this->assertNotEmpty($audit);
    }

    /** Handles the test failed login is recorded as security event without changing login error contract operation for the current WorkIntel workflow. */ public function test_failed_login_is_recorded_as_security_event_without_changing_login_error_contract(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->postJson('/api/v1/auth/login',['email'=>'owner@acme.test','password'=>'wrong-password'])->assertUnprocessable();
        $this->assertDatabaseHas('security_events',['event_type'=>'auth.login_failed','severity'=>'warning']);
    }
}
