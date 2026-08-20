<?php

namespace Tests\Feature;

use App\Models\SystemObservabilityAlert;
use App\Models\SystemObservabilityAlertRule;
use App\Models\User;
use App\Services\Observability\ObservabilityService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Exercises seller observability, privacy redaction, event dedupe and alert lifecycle flows. */
class ObservabilityAuditOperationsFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seed a complete platform before each observability flow. */
    protected function setUp(): void
    {
        parent::setUp();$this->seed(DatabaseSeeder::class);
    }

    /** Verify repeated event fingerprints aggregate and credential-shaped context values are redacted. */
    public function test_event_capture_deduplicates_and_redacts_sensitive_context(): void
    {
        $service=app(ObservabilityService::class);
        $first=$service->record('runtime','runtime.test','Synthetic production failure.','error',['authorization'=>'Bearer super-secret','password'=>'private','safe'=>'visible']);
        $second=$service->record('runtime','runtime.test','Synthetic production failure.','error',['token'=>'abc','safe'=>'visible']);
        $this->assertNotNull($first);$this->assertSame($first->id,$second?->id);$row=$first->fresh();$this->assertSame(2,$row->occurrence_count);$this->assertSame('[REDACTED]',$row->context['token']);$this->assertSame('visible',$row->context['safe']);
    }

    /** Verify platform operators can read health, tune a rule and acknowledge/resolve generated alerts. */
    public function test_operator_can_manage_observability_alert_lifecycle(): void
    {
        $owner=User::where('email','owner@acme.test')->firstOrFail();Sanctum::actingAs($owner);
        $overview=$this->getJson('/api/v1/seller/observability')->assertOk();$ruleId=$overview->json('rules.0.id');$this->assertNotNull($ruleId);
        $this->putJson('/api/v1/seller/observability/rules/'.$ruleId,['operator'=>'>=','threshold'=>0,'window_minutes'=>15,'severity'=>'warning','enabled'=>true,'cooldown_minutes'=>1,'channels'=>['dashboard']])->assertOk();
        $this->postJson('/api/v1/seller/observability/evaluate')->assertOk();$alert=SystemObservabilityAlert::query()->where('alert_rule_id',$ruleId)->latest()->firstOrFail();
        $this->postJson('/api/v1/seller/observability/alerts/'.$alert->id.'/acknowledge')->assertOk()->assertJsonPath('data.status','acknowledged');
        $this->postJson('/api/v1/seller/observability/alerts/'.$alert->id.'/resolve')->assertOk()->assertJsonPath('data.status','resolved');
    }

    /** Verify normal workspace members cannot read seller-level production telemetry. */
    public function test_non_operator_cannot_read_platform_observability(): void
    {
        $employee=User::where('email','employee@acme.test')->firstOrFail();Sanctum::actingAs($employee);$this->getJson('/api/v1/seller/observability')->assertForbidden();
    }
}
