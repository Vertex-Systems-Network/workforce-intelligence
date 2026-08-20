<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides attendance phase15 flow test behavior within the WorkIntel application. */ class AttendancePolicyFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test location policy events and corrections work end to end operation for the current WorkIntel workflow. */ public function test_location_policy_events_and_corrections_work_end_to_end(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $this->putJson('/api/v1/attendance/settings', [
            'allow_web' => true, 'allow_mobile' => true,
            'require_geolocation' => true, 'require_geofence' => true,
            'max_accuracy_meters' => 100, 'correction_window_days' => 14,
            'missed_clock_out_hours' => 16, 'auto_flag_missed_clock_out' => true,
            'allow_employee_corrections' => true,
        ], $headers)->assertOk();

        $this->postJson('/api/v1/attendance/locations', [
            'name' => 'QA Office', 'latitude' => 25.2048, 'longitude' => 55.2708,
            'radius_meters' => 200, 'status' => 'active',
        ], $headers)->assertCreated();

        $this->postJson('/api/v1/attendance/clock-in', [], $headers)->assertUnprocessable();
        $this->postJson('/api/v1/attendance/clock-in', [
            'source' => 'mobile', 'latitude' => 24.0, 'longitude' => 54.0, 'accuracy_meters' => 20,
        ], $headers)->assertUnprocessable();

        $clockIn = $this->postJson('/api/v1/attendance/clock-in', [
            'source' => 'mobile', 'latitude' => 25.2048, 'longitude' => 55.2708, 'accuracy_meters' => 15,
        ], $headers)->assertOk();
        $this->assertTrue((bool) $clockIn->json('location_check.within_geofence'));
        $this->assertDatabaseHas('attendance_events', [
            'workspace_id' => $membership->workspace_id,
            'member_id' => $membership->id,
            'event_type' => 'clock_in',
            'source' => 'mobile',
        ]);

        $date = now()->subDay()->toDateString();
        $correction = $this->postJson('/api/v1/attendance/corrections', [
            'date' => $date,
            'requested_clock_in_at' => $date.' 09:05:00',
            'requested_clock_out_at' => $date.' 18:10:00',
            'reason' => 'Forgot to clock out after leaving the office.',
        ], $headers)->assertCreated()->json('data');

        $this->patchJson('/api/v1/attendance/corrections/'.$correction['id'].'/review', [
            'status' => 'approved', 'review_note' => 'Verified by manager.',
        ], $headers)->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('attendance_records', [
            'workspace_id' => $membership->workspace_id,
            'member_id' => $membership->id,
            'date' => $date,
            'source' => 'manual',
        ]);
        $this->assertTrue(AttendanceEvent::where('event_type', 'correction_approved')->exists());
    }

    /** Handles the test missed clock out is flagged and security logging never breaks login operation for the current WorkIntel workflow. */ public function test_missed_clock_out_is_flagged_and_security_logging_never_breaks_login(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $employee = User::where('email', 'priya@acme.test')->firstOrFail();
        $member = $employee->memberships()->firstOrFail();

        AttendanceRecord::create([
            'workspace_id' => $member->workspace_id,
            'member_id' => $member->id,
            'date' => now()->subDay()->toDateString(),
            'clock_in_at' => now()->subHours(20),
            'status' => 'present',
            'source' => 'web',
        ]);
        Artisan::call('workintel:attendance-maintenance');
        $this->assertDatabaseHas('attendance_records', [
            'workspace_id' => $member->workspace_id,
            'member_id' => $member->id,
            'flag_type' => 'missing_clock_out',
            'status' => 'missing_clock_out',
        ]);

        Schema::drop('security_events');
        $this->postJson('/api/v1/auth/login', [
            'email' => $owner->email,
            'password' => 'definitely-wrong-password',
        ])->assertUnprocessable();
    }
}
