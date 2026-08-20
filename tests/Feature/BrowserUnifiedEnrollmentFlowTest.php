<?php

namespace Tests\Feature;

use App\Models\AgentEnrollment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides browser unified enrollment flow test behavior within the WorkIntel application. */ class BrowserUnifiedEnrollmentFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test desktop enrollment code can enroll browser once without consuming desktop use operation for the current WorkIntel workflow. */ public function test_desktop_enrollment_code_can_enroll_browser_once_without_consuming_desktop_use(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner=User::where('email','owner@acme.test')->firstOrFail();
        $membership=$owner->memberships()->firstOrFail();
        $employee=User::where('email','employee@acme.test')->firstOrFail()->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers=['X-Workspace-Id'=>(string)$membership->workspace_id];

        $code=$this->postJson('/api/v1/devices/enrollments',['member_id'=>$employee->id,'expires_minutes'=>10],$headers)->assertCreated()->json('enrollment_code');
        $this->assertStringStartsWith('WI-',$code);

        $browser=$this->postJson('/api/v1/browser/enroll',[
            'enrollment_code'=>$code,'installation_id'=>'browser-from-wi-1','browser_name'=>'Chrome','browser_version'=>'150.0','extension_version'=>'0.1.0',
        ])->assertCreated();
        $this->assertStringStartsWith('wib_',$browser->json('access_token'));

        $normalized=strtoupper(preg_replace('/[^A-Z0-9]/i','',$code));
        $enrollment=AgentEnrollment::where('code_hash',hash('sha256',$normalized))->firstOrFail();
        $this->assertNotNull($enrollment->browser_used_at);
        $this->assertNull($enrollment->used_at);

        $this->postJson('/api/v1/browser/enroll',[
            'enrollment_code'=>$code,'installation_id'=>'browser-from-wi-2','browser_name'=>'Chrome','browser_version'=>'150.0','extension_version'=>'0.1.0',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/agent/enroll',[
            'enrollment_code'=>$code,'installation_id'=>'desktop-from-same-wi','name'=>'Same Code Desktop','platform'=>'windows','os_name'=>'Windows 11','agent_version'=>'0.1.0',
        ])->assertCreated();

        $this->assertNotNull($enrollment->fresh()->used_at);
    }
}
