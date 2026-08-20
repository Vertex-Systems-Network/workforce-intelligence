<?php

namespace Tests\Feature;

use App\Models\IntelligenceInsight;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides intelligence phase25 flow test behavior within the WorkIntel application. */ class IntelligenceFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test explainable overtime signal is created and auto resolved operation for the current WorkIntel workflow. */ public function test_explainable_overtime_signal_is_created_and_auto_resolved(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $employee = User::where('email', 'employee@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $employeeMember = $employee->memberships()->where('workspace_id', $membership->workspace_id)->firstOrFail();
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $shift = Shift::updateOrCreate(
            ['workspace_id' => $membership->workspace_id, 'name' => 'Phase25 Long Shift'],
            ['start_time'=>'08:00:00','end_time'=>'20:00:00','break_minutes'=>60,'grace_minutes'=>0,'location_type'=>'office','timezone'=>'UTC','status'=>'active']
        );
        $start = now()->startOfWeek();
        $createdIds = [];
        foreach (range(0, 4) as $day) {
            $assignment = ShiftAssignment::updateOrCreate(
                ['workspace_id'=>$membership->workspace_id,'member_id'=>$employeeMember->id,'date'=>$start->copy()->addDays($day)->toDateString()],
                ['shift_id'=>$shift->id,'work_mode'=>'office','status'=>'published','published_at'=>now(),'published_by'=>$owner->id]
            );
            $createdIds[] = $assignment->id;
        }

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/intelligence/run', [], $headers)->assertOk();
        $signal = IntelligenceInsight::where('workspace_id',$membership->workspace_id)
            ->where('scope_type','member')->where('scope_id',$employeeMember->id)
            ->where('insight_type','overtime.weekly_risk')->firstOrFail();
        $this->assertSame('open', $signal->status);
        $this->assertGreaterThan(40, (float) data_get($signal->metrics, 'scheduled_hours'));
        $this->assertNotEmpty($signal->explanation);
        $this->assertNotEmpty($signal->source_refs);
        $this->assertNotEmpty($signal->recommendations);

        ShiftAssignment::whereIn('id', $createdIds)->delete();
        $this->postJson('/api/v1/intelligence/run', [], $headers)->assertOk();
        $this->assertSame('resolved', $signal->fresh()->status);
    }

    /** Handles the test employee and manager scope hide unrelated or sensitive signals operation for the current WorkIntel workflow. */ public function test_employee_and_manager_scope_hide_unrelated_or_sensitive_signals(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email','owner@acme.test')->firstOrFail();
        $manager = User::where('email','manager@acme.test')->firstOrFail();
        $employee = User::where('email','employee@acme.test')->firstOrFail();
        $workspaceId = $owner->memberships()->firstOrFail()->workspace_id;
        $employeeMember = $employee->memberships()->where('workspace_id',$workspaceId)->firstOrFail();

        IntelligenceInsight::create([
            'uuid'=>(string)Str::uuid(),'workspace_id'=>$workspaceId,'fingerprint'=>hash('sha256','p25-payroll-sensitive'),
            'category'=>'payroll','insight_type'=>'payroll.test_sensitive','scope_type'=>'member','scope_id'=>$employeeMember->id,'scope_label'=>'Sensitive Employee',
            'severity'=>'warning','title'=>'Sensitive payroll signal','summary'=>'Test','explanation'=>'Test payroll evidence','metrics'=>['net_pay'=>1234],
            'source_refs'=>[['type'=>'payroll_run','id'=>999]],'recommendations'=>['Review payroll'], 'status'=>'open','auto_resolve'=>false,
            'detected_at'=>now(),'last_detected_at'=>now(),
        ]);
        $otherTeam = Team::create(['workspace_id'=>$workspaceId,'name'=>'Phase25 Other Team','code'=>'P25-X','status'=>'active']);
        IntelligenceInsight::create([
            'uuid'=>(string)Str::uuid(),'workspace_id'=>$workspaceId,'fingerprint'=>hash('sha256','p25-other-team'),
            'category'=>'capacity','insight_type'=>'workload.team_imbalance','scope_type'=>'team','scope_id'=>$otherTeam->id,'scope_label'=>$otherTeam->name,
            'severity'=>'info','title'=>'Other team signal','summary'=>'Test','explanation'=>'Test team evidence','metrics'=>['spread'=>50],
            'source_refs'=>[['type'=>'team','id'=>$otherTeam->id]],'recommendations'=>['Review team'], 'status'=>'open','auto_resolve'=>false,
            'detected_at'=>now(),'last_detected_at'=>now(),
        ]);

        Sanctum::actingAs($employee);
        $employeeOverview = $this->getJson('/api/v1/intelligence/overview',['X-Workspace-Id'=>(string)$workspaceId])->assertOk();
        $this->assertFalse(collect($employeeOverview->json('insights'))->contains(fn($i)=>$i['category']==='payroll'));
        $this->assertTrue(collect($employeeOverview->json('insights'))->every(fn($i)=>$i['scope_type']==='member' && (int)$i['scope_id']===(int)$employeeMember->id));

        Sanctum::actingAs($manager);
        $managerOverview = $this->getJson('/api/v1/intelligence/overview',['X-Workspace-Id'=>(string)$workspaceId])->assertOk();
        $this->assertFalse(collect($managerOverview->json('insights'))->contains(fn($i)=>$i['category']==='payroll'));
        $this->assertFalse(collect($managerOverview->json('insights'))->contains(fn($i)=>$i['scope_type']==='team' && (int)$i['scope_id']===(int)$otherTeam->id));
    }

    /** Handles the test owner can update rule and acknowledge signal operation for the current WorkIntel workflow. */ public function test_owner_can_update_rule_and_acknowledge_signal(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email','owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $headers = ['X-Workspace-Id'=>(string)$membership->workspace_id];
        Sanctum::actingAs($owner);

        $overview = $this->getJson('/api/v1/intelligence/overview',$headers)->assertOk();
        $rule = collect($overview->json('rules'))->firstWhere('rule_key','capacity.overloaded');
        $this->assertNotNull($rule);
        $this->patchJson('/api/v1/intelligence/rules/'.$rule['id'], [
            'status'=>'active','severity'=>'danger','window_days'=>7,'threshold_value'=>115,'threshold_secondary'=>null,
        ], $headers)->assertOk()->assertJsonPath('data.severity','danger');

        $insight = IntelligenceInsight::where('workspace_id',$membership->workspace_id)->whereIn('status',['open','acknowledged'])->first();
        if ($insight) {
            $this->patchJson('/api/v1/intelligence/insights/'.$insight->id.'/status',['action'=>'acknowledge'],$headers)
                ->assertOk()->assertJsonPath('data.status','acknowledged');
        }
    }
}
