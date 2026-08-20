<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides organization and attendance test behavior within the WorkIntel application. */ class OrganizationAndAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test owner can manage organization shifts attendance and leave operation for the current WorkIntel workflow. */ public function test_owner_can_manage_organization_shifts_attendance_and_leave(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $organization = $this->getJson('/api/v1/organization', $headers)
            ->assertOk();

        $employeeId = (int) collect($organization->json('people'))
            ->first(fn (array $person) => (int) $person['id'] !== $membership->id)['id'];

        $departmentId = $this->postJson('/api/v1/organization/departments', [
            'name' => 'Customer Success',
            'code' => 'CS',
        ], $headers)->assertCreated()->json('data.id');

        $jobTitleId = $this->postJson('/api/v1/organization/job-titles', [
            'name' => 'Customer Success Manager',
            'code' => 'CSM',
            'status' => 'active',
        ], $headers)->assertCreated()->json('data.id');

        $team = $this->postJson('/api/v1/organization/teams', [
            'name' => 'Customer Experience',
            'code' => 'CX',
            'department_id' => $departmentId,
            'lead_id' => $employeeId,
            'member_ids' => [$employeeId],
            'status' => 'active',
        ], $headers)->assertCreated();

        $team->assertJsonPath('data.department.id', $departmentId);
        $this->assertDatabaseHas('job_titles', ['id' => $jobTitleId, 'workspace_id' => $membership->workspace_id]);
        $this->assertDatabaseHas('team_members', ['team_id' => $team->json('data.id'), 'member_id' => $employeeId]);

        $shiftId = $this->postJson('/api/v1/shifts', [
            'name' => 'QA Day',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_minutes' => 60,
            'grace_minutes' => 10,
            'location_type' => 'hybrid',
            'status' => 'active',
        ], $headers)->assertCreated()->json('data.id');

        $date = now()->toDateString();
        $this->postJson('/api/v1/shift-assignments', [
            'shift_id' => $shiftId,
            'member_ids' => [$membership->id],
            'dates' => [$date],
            'work_mode' => 'hybrid',
        ], $headers)->assertOk()->assertJsonPath('assigned', 1);

        $clockIn = $this->postJson('/api/v1/attendance/clock-in', [], $headers)
            ->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'id' => $clockIn->json('data.id'),
            'workspace_id' => $membership->workspace_id,
            'member_id' => $membership->id,
        ]);

        $leaveTypeId = $this->postJson('/api/v1/leave/types', [
            'name' => 'Volunteer Day',
            'code' => 'VOL',
            'is_paid' => true,
            'annual_allowance_days' => 2,
        ], $headers)->assertCreated()->json('data.id');

        $leaveId = $this->postJson('/api/v1/leave', [
            'leave_type_id' => $leaveTypeId,
            'start_date' => now()->addDays(14)->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
            'reason' => 'Community volunteering',
        ], $headers)->assertCreated()->json('data.id');

        $this->patchJson('/api/v1/leave/'.$leaveId.'/review', [
            'status' => 'approved',
            'review_note' => 'Approved for testing.',
        ], $headers)->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertSame('approved', LeaveRequest::findOrFail($leaveId)->status);
        $this->assertNotNull(AttendanceRecord::findOrFail($clockIn->json('data.id'))->clock_in_at);
    }
}
