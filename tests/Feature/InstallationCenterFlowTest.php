<?php
namespace Tests\Feature;
use App\Models\AgentEnrollment;
use App\Models\InstallationGuideProgress;
use App\Models\User;
use App\Services\Installation\ConfiguredReleaseBundleService;
use App\Services\Releases\ReleaseCatalogService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use PharData;
use Tests\TestCase;
/** Provides p9 installation center flow test behavior within the WorkIntel application. */ class InstallationCenterFlowTest extends TestCase
{
    use RefreshDatabase;
    /** Handles the set up operation for the current WorkIntel workflow. */ protected function setUp():void{parent::setUp();$this->seed(DatabaseSeeder::class);}
    /** Handles the test employee can open installation center and save own progress operation for the current WorkIntel workflow. */ public function test_employee_can_open_installation_center_and_save_own_progress():void
    {
        $user=User::where('email','employee@acme.test')->firstOrFail();$member=$user->memberships()->where('status','active')->firstOrFail();Sanctum::actingAs($user);$h=['X-Workspace-Id'=>(string)$member->workspace_id];
        $this->getJson('/api/v1/installation-center',$h)->assertOk()->assertJsonCount(7,'guides')->assertJsonPath('server_url','http://localhost');
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
    /** Proves server-bound deployment copies contain only the runtime origin while canonical release bytes remain unchanged. */ public function test_server_bound_download_bundle_preserves_canonical_release_and_contains_no_enrollment_secret():void
    {
        $catalog=app(ReleaseCatalogService::class);$release=$catalog->find('agent-windows-x64');$this->assertNotNull($release);$canonical=$catalog->absolutePath($release);$this->assertNotNull($canonical);$before=hash_file('sha256',$canonical);
        $bundle=app(ConfiguredReleaseBundleService::class)->build('agent-windows-x64','https://runtime.example.test');
        try{
            $archive=new PharData($bundle['path']);$entry=$archive['desktop-agent/workintel-server.txt'];$this->assertSame("https://runtime.example.test\n",$entry->getContent());$this->assertStringNotContainsString('WI-',$entry->getContent());$this->assertStringNotContainsString('token',$entry->getContent());unset($archive);
        }finally{File::delete($bundle['path']);}
        $this->assertSame($before,hash_file('sha256',$canonical));
    }
    /** Proves authenticated dashboard downloads bind the package to the request-time WorkIntel origin. */ public function test_dashboard_release_download_uses_request_runtime_origin():void
    {
        $user=User::where('email','owner@acme.test')->firstOrFail();Sanctum::actingAs($user);
        $this->withServerVariables(['HTTPS'=>'on','HTTP_HOST'=>'runtime.example.test'])->get('/api/v1/releases/browser-chrome-edge/download')
            ->assertOk()->assertHeader('X-WorkIntel-Configured-Server','https://runtime.example.test')->assertHeader('Cache-Control','private, no-store, max-age=0');
    }
}
