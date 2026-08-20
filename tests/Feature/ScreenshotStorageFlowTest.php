<?php

namespace Tests\Feature;

use App\Models\Screenshot;
use App\Models\ScreenshotStorageProvider;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\ScreenshotStorage\ScreenshotStorageService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides p8 screenshot storage flow test behavior within the WorkIntel application. */ class ScreenshotStorageFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the set up operation for the current WorkIntel workflow. */ protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Handles the test owner sees local storage and can create encrypted s3 provider operation for the current WorkIntel workflow. */ public function test_owner_sees_local_storage_and_can_create_encrypted_s3_provider(): void
    {
        [$owner,$member]=$this->userAndMember('owner@acme.test');Sanctum::actingAs($owner);$headers=$this->headers($member->workspace_id);
        $this->getJson('/api/v1/screenshots/storage/providers',$headers)->assertOk()->assertJsonPath('providers.0.provider_type','local');
        $created=$this->postJson('/api/v1/screenshots/storage/providers',[
            'name'=>'R2 Archive','provider_type'=>'s3','root_path'=>'screenshots','fallback_to_local'=>true,'delete_local_after_sync'=>false,
            'config'=>['access_key'=>'abc123','secret_key'=>'super-secret-value','region'=>'auto','bucket'=>'screens','endpoint'=>'https://example.r2.cloudflarestorage.com'],
        ],$headers)->assertCreated()->json('provider');
        $this->assertSame('R2 Archive',$created['name']);
        $this->assertContains('secret_key',$created['configured_secret_fields']);
        $raw=(string)DB::table('screenshot_storage_providers')->where('id',$created['id'])->value('encrypted_config');
        $this->assertStringNotContainsString('super-secret-value',$raw);
    }

    /** Handles the test owner can change notification policy and keep one minute interval operation for the current WorkIntel workflow. */ public function test_owner_can_change_notification_policy_and_keep_one_minute_interval(): void
    {
        [$owner,$member]=$this->userAndMember('owner@acme.test');Sanctum::actingAs($owner);$headers=$this->headers($member->workspace_id);
        $response=$this->putJson('/api/v1/screenshots/settings',[
            'enabled'=>true,'interval_minutes'=>1,'randomize_minutes'=>0,'capture_all_monitors'=>false,'blur_by_default'=>false,'quality'=>'medium','allow_employee_delete'=>false,'retention_days'=>7,'max_upload_kb'=>4096,
            'capture_notification_mode'=>'first_session','notify_on_upload_failure'=>true,
        ],$headers)->assertOk();
        $response->assertJsonPath('settings.interval_minutes',1)->assertJsonPath('settings.capture_notification_mode','first_session');
    }

    /** Handles the test employee cannot manage storage providers operation for the current WorkIntel workflow. */ public function test_employee_cannot_manage_storage_providers(): void
    {
        [$employee,$member]=$this->userAndMember('employee@acme.test');Sanctum::actingAs($employee);
        $this->getJson('/api/v1/screenshots/storage/providers',$this->headers($member->workspace_id))->assertForbidden();
    }

    /** Handles the test local enqueue calculates checksum and keeps private local file operation for the current WorkIntel workflow. */ public function test_local_enqueue_calculates_checksum_and_keeps_private_local_file(): void
    {
        Storage::fake('local');[$owner,$member]=$this->userAndMember('owner@acme.test');$workspace=$member->workspace()->firstOrFail();
        $provider=ScreenshotStorageProvider::where('workspace_id',$workspace->id)->where('provider_type','local')->firstOrFail();$provider->update(['is_primary'=>true]);
        $path='screenshots/'.$workspace->id.'/2026/08/12/test.png';Storage::disk('local')->put($path,'binary-image');
        $shot=Screenshot::create(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'workspace_id'=>$workspace->id,'member_id'=>$member->id,'disk'=>'local','path'=>$path,'mime_type'=>'image/png','size_bytes'=>12,'monitor_index'=>1,'captured_at'=>now()]);
        app(ScreenshotStorageService::class)->enqueue($shot);$shot->refresh();
        $this->assertSame('local',$shot->storage_status);$this->assertSame($provider->id,$shot->storage_provider_id);$this->assertSame(hash('sha256','binary-image'),$shot->checksum_sha256);Storage::disk('local')->assertExists($path);
    }

    /** Handles the test active remote provider cannot be deleted while it stores live screenshots operation for the current WorkIntel workflow. */ public function test_active_remote_provider_cannot_be_deleted_while_it_stores_live_screenshots(): void
    {
        [$owner,$member]=$this->userAndMember('owner@acme.test');Sanctum::actingAs($owner);$headers=$this->headers($member->workspace_id);
        $provider=ScreenshotStorageProvider::create(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'workspace_id'=>$member->workspace_id,'name'=>'Remote','provider_type'=>'s3','enabled'=>true,'is_primary'=>false,'fallback_to_local'=>true,'encrypted_config'=>['access_key'=>'a','secret_key'=>'b','region'=>'us-east-1','bucket'=>'b']]);
        Screenshot::create(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'workspace_id'=>$member->workspace_id,'member_id'=>$member->id,'disk'=>'local','path'=>'x','mime_type'=>'image/png','size_bytes'=>1,'monitor_index'=>1,'captured_at'=>now(),'storage_provider_id'=>$provider->id,'storage_status'=>'remote','remote_key'=>'screenshots/x.png']);
        $this->deleteJson('/api/v1/screenshots/storage/providers/'.$provider->id,[],$headers)->assertStatus(409);
    }

    /** Handles the user and member operation for the current WorkIntel workflow. */ private function userAndMember(string $email): array
    {
        $user=User::where('email',$email)->firstOrFail();$member=WorkspaceMember::where('user_id',$user->id)->where('status','active')->firstOrFail();return[$user,$member];
    }
    /** Handles the headers operation for the current WorkIntel workflow. */ private function headers(int $workspaceId): array { return ['X-Workspace-Id'=>(string)$workspaceId]; }
}
