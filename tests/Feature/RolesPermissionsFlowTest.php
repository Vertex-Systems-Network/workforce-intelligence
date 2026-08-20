<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkspaceMember;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides p2 roles permissions flow test behavior within the WorkIntel application. */ class RolesPermissionsFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the set up operation for the current WorkIntel workflow. */ protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Handles the test owner can create custom role and assign multiple roles with primary role operation for the current WorkIntel workflow. */ public function test_owner_can_create_custom_role_and_assign_multiple_roles_with_primary_role(): void
    {
        [$owner,$ownerMember]=$this->userAndMember('owner@acme.test');
        [$employee,$employeeMember]=$this->userAndMember('employee@acme.test');
        Sanctum::actingAs($owner);$headers=$this->headers($ownerMember->workspace_id);

        $role=$this->postJson('/api/v1/access-control/roles',[
            'name'=>'Regional Coordinator','template_key'=>'project-coordinator',
        ],$headers)->assertCreated()->json('data');
        $employeeRole=Role::where('workspace_id',$ownerMember->workspace_id)->where('slug','employee')->firstOrFail();

        $this->putJson('/api/v1/access-control/members/'.$employeeMember->id.'/roles',[
            'role_ids'=>[$employeeRole->id,$role['id']], 'primary_role_id'=>$role['id'],
        ],$headers)->assertOk()->assertJsonPath('data.primary_role_id',$role['id']);

        $employeeMember->refresh();
        $this->assertCount(2,$employeeMember->roles);
        $this->assertTrue($employeeMember->hasPermission('tasks.manage_team'));
    }

    /** Handles the test explicit permission deny and module deny win across multiple roles operation for the current WorkIntel workflow. */ public function test_explicit_permission_deny_and_module_deny_win_across_multiple_roles(): void
    {
        [$owner,$ownerMember]=$this->userAndMember('owner@acme.test');
        [, $employeeMember]=$this->userAndMember('employee@acme.test');
        Sanctum::actingAs($owner);$headers=$this->headers($ownerMember->workspace_id);

        $allow=$this->postJson('/api/v1/access-control/roles',['name'=>'Reports Reader'],$headers)->assertCreated()->json('data');
        $deny=$this->postJson('/api/v1/access-control/roles',['name'=>'Restricted Reports'],$headers)->assertCreated()->json('data');
        $this->putJson('/api/v1/access-control/roles/'.$allow['id'],['permission_rules'=>['reports.view'=>'allow']],$headers)->assertOk();
        $this->putJson('/api/v1/access-control/roles/'.$deny['id'],['permission_rules'=>['reports.view'=>'deny']],$headers)->assertOk();
        $this->putJson('/api/v1/access-control/members/'.$employeeMember->id.'/roles',['role_ids'=>[$allow['id'],$deny['id']],'primary_role_id'=>$allow['id']],$headers)->assertOk();
        $employeeMember->unsetRelation('roles');
        $this->assertFalse($employeeMember->hasPermission('reports.view'));

        $this->putJson('/api/v1/access-control/roles/'.$deny['id'],['permission_rules'=>[],'modules'=>['reports'=>'deny']],$headers)->assertOk();
        $employeeMember->unsetRelation('roles');
        $this->assertFalse($employeeMember->hasPermission('reports.view'));
    }

    /** Handles the test department data scope constrains people even with view all permission operation for the current WorkIntel workflow. */ public function test_department_data_scope_constrains_people_even_with_view_all_permission(): void
    {
        [$owner,$ownerMember]=$this->userAndMember('owner@acme.test');
        [, $coordinator]=$this->userAndMember('coordinator@acme.test');
        [, $employee]=$this->userAndMember('employee@acme.test');
        $this->assertNotNull($employee->department_id);
        Sanctum::actingAs($owner);$headers=$this->headers($ownerMember->workspace_id);

        $role=$this->postJson('/api/v1/access-control/roles',['name'=>'Department Auditor'],$headers)->assertCreated()->json('data');
        $this->putJson('/api/v1/access-control/roles/'.$role['id'],[
            'permission_rules'=>['people.view_all'=>'allow'],
            'scopes'=>['people'=>['scope_type'=>'department','scope_ids'=>[$employee->department_id]]],
        ],$headers)->assertOk();
        $this->putJson('/api/v1/access-control/members/'.$coordinator->id.'/roles',['role_ids'=>[$role['id']],'primary_role_id'=>$role['id']],$headers)->assertOk();

        Sanctum::actingAs($coordinator->user);$response=$this->getJson('/api/v1/people',$headers)->assertOk();
        foreach($response->json('data') as $row)$this->assertSame($employee->department_id,$row['department_id']);
    }

    /** Handles the test workspace owner cannot lose owner role and custom role must be unused before archive operation for the current WorkIntel workflow. */ public function test_workspace_owner_cannot_lose_owner_role_and_custom_role_must_be_unused_before_archive(): void
    {
        [$owner,$ownerMember]=$this->userAndMember('owner@acme.test');
        [, $employeeMember]=$this->userAndMember('employee@acme.test');
        Sanctum::actingAs($owner);$headers=$this->headers($ownerMember->workspace_id);
        $employeeRole=Role::where('workspace_id',$ownerMember->workspace_id)->where('slug','employee')->firstOrFail();
        $this->putJson('/api/v1/access-control/members/'.$ownerMember->id.'/roles',['role_ids'=>[$employeeRole->id],'primary_role_id'=>$employeeRole->id],$headers)->assertStatus(422);

        $custom=$this->postJson('/api/v1/access-control/roles',['name'=>'Temporary Role'],$headers)->assertCreated()->json('data');
        $this->putJson('/api/v1/access-control/members/'.$employeeMember->id.'/roles',['role_ids'=>[$custom['id']],'primary_role_id'=>$custom['id']],$headers)->assertOk();
        $this->postJson('/api/v1/access-control/roles/'.$custom['id'].'/archive',[],$headers)->assertStatus(422);
    }

    /** Handles the user and member operation for the current WorkIntel workflow. */ private function userAndMember(string $email):array
    {
        $user=User::where('email',$email)->firstOrFail();$member=$user->memberships()->where('status','active')->firstOrFail();return[$user,$member];
    }
    /** Handles the headers operation for the current WorkIntel workflow. */ private function headers(int $workspaceId):array{return['X-Workspace-Id'=>(string)$workspaceId];}
}
