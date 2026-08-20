<?php

namespace Tests\Feature;

use App\Models\OpenShift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides scheduling phase16 flow test behavior within the WorkIntel application. */ class SchedulingFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test manager can plan publish and review schedule while employee can claim and swap operation for the current WorkIntel workflow. */ public function test_manager_can_plan_publish_and_review_schedule_while_employee_can_claim_and_swap(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager=User::where('email','manager@acme.test')->firstOrFail();$managerMember=$manager->memberships()->with('roles.permissions')->firstOrFail();
        Sanctum::actingAs($manager);$headers=['X-Workspace-Id'=>(string)$managerMember->workspace_id];
        $week=$this->getJson('/api/v1/scheduling/week?start=2026-08-10',$headers)->assertOk();
        $this->assertTrue($week->json('can_manage'));$this->assertNotEmpty($week->json('people'));$this->assertArrayHasKey('warnings',$week->json('analysis'));

        $employee=User::where('email','employee@acme.test')->firstOrFail();$employeeMember=$employee->memberships()->with('roles.permissions')->firstOrFail();
        Sanctum::actingAs($employee);$employeeHeaders=['X-Workspace-Id'=>(string)$employeeMember->workspace_id];
        $this->putJson('/api/v1/scheduling/availability',['date'=>'2026-08-13','status'=>'preferred','start_time'=>'09:00','end_time'=>'18:00','note'=>'Available for release coverage.'],$employeeHeaders)->assertCreated();
        $open=OpenShift::where('workspace_id',$employeeMember->workspace_id)->whereDate('date','2026-08-13')->firstOrFail();
        $this->postJson('/api/v1/scheduling/open-shifts/'.$open->id.'/claim',[],$employeeHeaders)->assertOk();
        $assignment=ShiftAssignment::where('workspace_id',$employeeMember->workspace_id)->where('member_id',$employeeMember->id)->whereDate('date','2026-08-13')->firstOrFail();
        $swap=$this->postJson('/api/v1/scheduling/swaps',['assignment_id'=>$assignment->id,'request_type'=>'drop','message'=>'Need the day free.'],$employeeHeaders)->assertCreated()->json('data');

        Sanctum::actingAs($manager);
        $this->patchJson('/api/v1/scheduling/swaps/'.$swap['id'].'/review',['decision'=>'approved','review_note'=>'Coverage confirmed.'],$headers)->assertOk()->assertJsonPath('data.status','approved');
        $this->assertDatabaseMissing('shift_assignments',['id'=>$assignment->id]);
    }

    /** Handles the test manager has project management after role repair and all internal demo roles exist operation for the current WorkIntel workflow. */ public function test_manager_has_project_management_after_role_repair_and_all_internal_demo_roles_exist(): void
    {
        $this->seed(DatabaseSeeder::class);
        foreach(['owner@acme.test','admin@acme.test','hr@acme.test','manager@acme.test','teamlead@acme.test','payroll@acme.test','employee@acme.test'] as $email){$this->assertDatabaseHas('users',['email'=>$email]);}
        $manager=User::where('email','manager@acme.test')->firstOrFail();$member=$manager->memberships()->with('roles.permissions')->firstOrFail();
        $this->assertTrue($member->hasPermission('projects.manage'));$this->assertTrue($member->hasPermission('scheduling.manage'));
    }
}
