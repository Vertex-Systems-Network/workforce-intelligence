<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides live workforce flow test behavior within the WorkIntel application. */ class LiveWorkforceFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test owner can read live presence and unified worker timeline operation for the current WorkIntel workflow. */ public function test_owner_can_read_live_presence_and_unified_worker_timeline(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $workspace = $owner->memberships()->firstOrFail()->workspace;
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $workspace->id];

        $response = $this->getJson('/api/v1/live-workforce', $headers)->assertOk();
        $this->assertNotEmpty($response->json('revision'));
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));

        $ahmed = collect($response->json('data'))->firstWhere('name', 'Ahmed Khan');
        $priya = collect($response->json('data'))->firstWhere('name', 'Priya Sharma');
        $marcus = collect($response->json('data'))->firstWhere('name', 'Marcus Webb');
        $this->assertSame('working', $ahmed['status']);
        $this->assertSame('meeting', $priya['status']);
        $this->assertSame('idle', $marcus['status']);

        $this->getJson('/api/v1/live-workforce/'.$ahmed['member_id'].'/timeline?from=2026-08-10&to=2026-08-10', $headers)
            ->assertOk()
            ->assertJsonPath('member.name', 'Ahmed Khan')
            ->assertJsonFragment(['type' => 'app.session'])
            ->assertJsonFragment(['type' => 'website.session']);
    }
}
