<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides role access ux flow test behavior within the WorkIntel application. */ class RoleAccessUxFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test auth payload exposes scoped role permissions and owner can manage editable roles operation for the current WorkIntel workflow. */ public function test_auth_payload_exposes_scoped_role_permissions_and_owner_can_manage_editable_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $me = $this->getJson('/api/v1/auth/me')->assertOk();
        $workspace = collect($me->json('user.workspaces'))->firstWhere('id', $membership->workspace_id);
        $this->assertSame('owner', $workspace['role']);
        $this->assertContains('settings.manage', $workspace['permissions']);
        $this->assertContains('tasks.view_all', $workspace['permissions']);

        $matrix = $this->getJson('/api/v1/access-control', $headers)->assertOk();
        $employeeRole = collect($matrix->json('roles'))->firstWhere('slug', 'employee');
        $adminRole = collect($matrix->json('roles'))->firstWhere('slug', 'admin');
        $this->assertTrue($employeeRole['editable']);
        $this->assertFalse($adminRole['editable']);

        $this->putJson('/api/v1/access-control/roles/'.$employeeRole['id'], [
            'permission_slugs' => ['attendance.view_own', 'tasks.view_own'],
        ], $headers)->assertOk();

        $this->assertSame(
            ['attendance.view_own', 'tasks.view_own'],
            Role::findOrFail($employeeRole['id'])->permissions()->orderBy('slug')->pluck('slug')->all()
        );
    }

    /** Handles the test employee only sees assigned projects own tasks and own attendance operation for the current WorkIntel workflow. */ public function test_employee_only_sees_assigned_projects_own_tasks_and_own_attendance(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('email', 'employee@acme.test')->firstOrFail();
        $member = $employee->memberships()->with(['roles.permissions'])->firstOrFail();
        Sanctum::actingAs($employee);
        $headers = ['X-Workspace-Id' => (string) $member->workspace_id];

        $projects = $this->getJson('/api/v1/projects', $headers)->assertOk()->json('data');
        $this->assertNotEmpty($projects);
        foreach ($projects as $project) {
            $this->assertNotNull(collect($project['members'])->firstWhere('id', $member->id));
            $this->assertNull($project['budget_amount']);
            $this->assertSame('none', $project['budget_type']);
        }

        $tasks = $this->getJson('/api/v1/tasks', $headers)->assertOk()->json('data');
        $this->assertNotEmpty($tasks);
        foreach ($tasks as $task) {
            $this->assertNotNull(collect($task['assignees'])->firstWhere('id', $member->id));
        }

        $attendance = $this->getJson('/api/v1/attendance', $headers)->assertOk();
        $this->assertCount(1, $attendance->json('rows'));
        $this->assertSame($member->id, $attendance->json('rows.0.member_id'));
        $this->getJson('/api/v1/people', $headers)->assertForbidden();
    }

    /** Handles the test manager people attendance and timesheets are team scoped operation for the current WorkIntel workflow. */ public function test_manager_people_attendance_and_timesheets_are_team_scoped(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@acme.test')->firstOrFail();
        $member = $manager->memberships()->with(['roles.permissions'])->firstOrFail();
        Sanctum::actingAs($manager);
        $headers = ['X-Workspace-Id' => (string) $member->workspace_id];

        $people = collect($this->getJson('/api/v1/people', $headers)->assertOk()->json('data'));
        $this->assertTrue($people->contains('email', 'marcus@acme.test'));
        $this->assertFalse($people->contains('email', 'priya@acme.test'));

        $attendanceRows = collect($this->getJson('/api/v1/attendance', $headers)->assertOk()->json('rows'));
        $this->assertTrue($attendanceRows->contains('name', 'Marcus Webb'));
        $this->assertFalse($attendanceRows->contains('name', 'Priya Sharma'));

        $timesheetRows = collect($this->getJson('/api/v1/timesheets/week?start=2026-08-10', $headers)->assertOk()->json('rows'));
        $this->assertTrue($timesheetRows->contains('name', 'Marcus Webb'));
        $this->assertFalse($timesheetRows->contains('name', 'Priya Sharma'));
    }
}
