<?php

namespace Tests\Feature;

use App\Models\TimeEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides timesheet flow test behavior within the WorkIntel application. */ class TimesheetFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test owner can read and approve seeded timesheets operation for the current WorkIntel workflow. */ public function test_owner_can_read_and_approve_seeded_timesheets(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);

        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $seededEntry = TimeEntry::query()
            ->where('workspace_id', $membership->workspace_id)
            ->orderBy('started_at')
            ->firstOrFail();

        $weekStart = Carbon::parse($seededEntry->date)
            ->startOfWeek(Carbon::MONDAY)
            ->toDateString();

        $response = $this->getJson('/api/v1/timesheets/week?start='.$weekStart, $headers)
            ->assertOk()
            ->assertJsonPath('week_start', $weekStart);

        $returnedEntry = collect($response->json('entries'))
            ->firstWhere('id', $seededEntry->id);

        $this->assertNotNull($returnedEntry);
        $entryId = $returnedEntry['id'];
        $this->assertSame($seededEntry->id, $entryId);

        $this->patchJson('/api/v1/timesheets/entries/'.$entryId.'/approval', [
            'status' => 'approved',
        ], $headers)->assertOk()->assertJsonPath('data.approval_status', 'approved');

        $this->assertSame('approved', TimeEntry::findOrFail($entryId)->approval_status);
    }
}
