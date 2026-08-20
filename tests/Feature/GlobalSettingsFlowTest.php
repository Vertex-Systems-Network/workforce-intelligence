<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkspacePreference;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides p3 global settings flow test behavior within the WorkIntel application. */ class GlobalSettingsFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the set up operation for the current WorkIntel workflow. */ protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Handles the test owner can update general settings and values persist to correct sources operation for the current WorkIntel workflow. */ public function test_owner_can_update_general_settings_and_values_persist_to_correct_sources(): void
    {
        [$owner,$member]=$this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);$headers=$this->headers($member->workspace_id);

        $this->getJson('/api/v1/settings/workspace',$headers)->assertOk()->assertJsonPath('can_manage',true);
        $this->putJson('/api/v1/settings/workspace/general',[
            'workspace_name'=>'Acme Global','company_name'=>'Acme Holdings','legal_name'=>'Acme Holdings LLC','website_url'=>'https://acme.test','support_email'=>'support@acme.test','support_phone'=>'+971500000000','address_line_1'=>'Business Bay','address_line_2'=>null,'city'=>'Dubai','state_region'=>'Dubai','postal_code'=>'00000','country'=>'AE','timezone'=>'Asia/Dubai','currency'=>'AED','week_starts_on'=>1,'default_language'=>'en','date_format'=>'DD/MM/YYYY','time_format'=>'24h','fiscal_year_start_month'=>4,'number_format'=>'1,234.56','decimal_separator'=>'.','thousands_separator'=>',',
        ],$headers)->assertOk()->assertJsonPath('data.currency','AED')->assertJsonPath('data.fiscal_year_start_month',4);

        $workspace=$member->workspace()->firstOrFail();
        $this->assertSame('Acme Global',$workspace->name);
        $this->assertSame('Asia/Dubai',$workspace->timezone);
        $this->assertSame('AED',$workspace->currency);
        $pref=WorkspacePreference::where('workspace_id',$workspace->id)->firstOrFail();
        $this->assertSame('Acme Holdings LLC',$pref->legal_name);
        $this->assertSame('DD/MM/YYYY',$pref->date_format);
    }

    /** Handles the test settings view role is read only and manage permission is required for mutation operation for the current WorkIntel workflow. */ public function test_settings_view_role_is_read_only_and_manage_permission_is_required_for_mutation(): void
    {
        [$owner,$ownerMember]=$this->userAndMember('owner@acme.test');[, $employeeMember]=$this->userAndMember('employee@acme.test');
        Sanctum::actingAs($owner);$headers=$this->headers($ownerMember->workspace_id);
        $role=$this->postJson('/api/v1/access-control/roles',['name'=>'Settings Reader'],$headers)->assertCreated()->json('data');
        $this->putJson('/api/v1/access-control/roles/'.$role['id'],['permission_rules'=>['settings.view'=>'allow']],$headers)->assertOk();
        $this->putJson('/api/v1/access-control/members/'.$employeeMember->id.'/roles',['role_ids'=>[$role['id']],'primary_role_id'=>$role['id']],$headers)->assertOk();

        Sanctum::actingAs($employeeMember->user);$this->getJson('/api/v1/settings/workspace',$headers)->assertOk()->assertJsonPath('can_manage',false);
        $this->putJson('/api/v1/settings/workspace/general',['workspace_name'=>'Forbidden'],$headers)->assertForbidden();
    }

    /** Handles the test owner can upload workspace logo and auth payload exposes settings operation for the current WorkIntel workflow. */ public function test_owner_can_upload_workspace_logo_and_auth_payload_exposes_settings(): void
    {
        Storage::fake('local');
        [$owner,$member]=$this->userAndMember('owner@acme.test');Sanctum::actingAs($owner);$headers=$this->headers($member->workspace_id);
        $this->post('/api/v1/settings/workspace/appearance',[
            'app_title'=>'Acme OS','accent_color'=>'#123456','secondary_color'=>'#22C55E','default_theme'=>'light','sidebar_density'=>'compact','login_title'=>'Welcome to Acme','login_subtitle'=>'One workspace for every team.','logo'=>UploadedFile::fake()->image('logo.png',120,120),
        ],$headers)->assertOk()->assertJsonPath('data.app_title','Acme OS');
        $pref=WorkspacePreference::where('workspace_id',$member->workspace_id)->firstOrFail();
        Storage::disk('local')->assertExists($pref->logo_path);

        $this->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('user.workspaces.0.settings.app_title','Acme OS')
            ->assertJsonPath('user.workspaces.0.settings.sidebar_density','compact');
    }

    /** Handles the user and member operation for the current WorkIntel workflow. */ private function userAndMember(string $email):array
    {
        $user=User::where('email',$email)->firstOrFail();$member=$user->memberships()->where('status','active')->firstOrFail();return[$user,$member];
    }
    /** Handles the headers operation for the current WorkIntel workflow. */ private function headers(int $workspaceId):array{return['X-Workspace-Id'=>(string)$workspaceId];}
}
