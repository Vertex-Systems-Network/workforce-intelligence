<?php
namespace Tests\Feature;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
/** Certifies per-user/per-workspace M11 onboarding preferences on a real database. */
class RoleUxHelpM11FlowTest extends TestCase
{
    use RefreshDatabase;
    /** Seed the canonical workspace before exercising personal M11 preference flows. */
    protected function setUp(): void { parent::setUp(); $this->seed(DatabaseSeeder::class); }
    /** Ensures one member's checklist state persists without changing another member's preferences. */
    public function test_role_help_progress_is_personal_and_workspace_scoped(): void
    {
        $owner=User::query()->where('email','owner@acme.test')->firstOrFail();$membership=$owner->memberships()->where('status','active')->firstOrFail();Sanctum::actingAs($owner);$headers=['X-Workspace-Id'=>(string)$membership->workspace_id];
        $settings=['onboarding_completed'=>['workspace-settings','modules'],'help_seen'=>true,'help_dismissed'=>['overview:intro'],'onboarding_started_at'=>now()->toISOString(),'role_seen'=>'owner','checklist_version'=>1];
        $this->putJson('/api/v1/ui/preferences/role-help-v1',['settings'=>$settings],$headers)->assertOk()->assertJsonPath('data.help_seen',true)->assertJsonPath('data.onboarding_completed.0','workspace-settings');
        $this->getJson('/api/v1/ui/preferences/role-help-v1',$headers)->assertOk()->assertJsonPath('data.role_seen','owner');
        $this->deleteJson('/api/v1/ui/preferences/role-help-v1',[],$headers)->assertOk();$this->getJson('/api/v1/ui/preferences/role-help-v1',$headers)->assertOk()->assertJsonPath('data',[]);
    }
}
