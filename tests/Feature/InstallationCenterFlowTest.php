<?php
namespace Tests\Feature;
use App\Models\AgentEnrollment;
use App\Models\InstallationGuideProgress;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
/** Provides p9 installation center flow test behavior within the WorkIntel application. */ class InstallationCenterFlowTest extends TestCase
{
    use RefreshDatabase;
    /** Handles the set up operation for the current WorkIntel workflow. */ protected function setUp():void{parent::setUp();$this->seed(DatabaseSeeder::class);}
    /** Handles the test employee can open installation center and save own progress operation for the current WorkIntel workflow. */ public function test_employee_can_open_installation_center_and_save_own_progress():void
    {
        $user=User::where('email','employee@acme.test')->firstOrFail();$member=$user->memberships()->where('status','active')->firstOrFail();Sanctum::actingAs($user);$h=['X-Workspace-Id'=>(string)$member->workspace_id];
        $this->getJson('/api/v1/installation-center',$h)->assertOk()->assertJsonCount(7,'guides');
        $this->putJson('/api/v1/installation-center/guides/windows-agent/progress',['completed_steps'=>['download','verify'],'current_step'=>'verify'],$h)->assertOk()->assertJsonPath('data.current_step','verify');
        $this->assertSame($member->id,InstallationGuideProgress::firstOrFail()->member_id);
    }
    /** Handles the test self enrollment is hash only and for current member operation for the current WorkIntel workflow. */ public function test_self_enrollment_is_hash_only_and_for_current_member():void
    {
        $user=User::where('email','employee@acme.test')->firstOrFail();$member=$user->memberships()->where('status','active')->firstOrFail();Sanctum::actingAs($user);$h=['X-Workspace-Id'=>(string)$member->workspace_id];
        $r=$this->postJson('/api/v1/installation-center/enrollment',['expires_minutes'=>15],$h)->assertCreated();$plain=$r->json('enrollment_code');$row=AgentEnrollment::latest('id')->firstOrFail();
        $this->assertSame($member->id,$row->member_id);$this->assertNotSame($plain,$row->code_hash);$this->assertSame(hash('sha256',preg_replace('/[^A-Z0-9]/','',strtoupper($plain))),$row->code_hash);
    }
    /** Handles the test pdf guide is downloadable operation for the current WorkIntel workflow. */ public function test_pdf_guide_is_downloadable():void
    {
        $user=User::where('email','owner@acme.test')->firstOrFail();$member=$user->memberships()->where('status','active')->firstOrFail();Sanctum::actingAs($user);$h=['X-Workspace-Id'=>(string)$member->workspace_id];$r=$this->get('/api/v1/installation-center/guides/windows-agent/pdf',$h);$r->assertOk()->assertHeader('content-type','application/pdf');$this->assertStringStartsWith('%PDF-',$r->getContent());
    }
}
