<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides production release flow test behavior within the WorkIntel application. */ class ProductionReleaseFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test health endpoints and release catalog are available operation for the current WorkIntel workflow. */ public function test_health_endpoints_and_release_catalog_are_available(): void
    {
        $this->getJson('/health/live')->assertOk()->assertJsonPath('ok', true);
        $this->getJson('/health/ready')->assertOk()->assertJsonPath('ok', true);

        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        Sanctum::actingAs($owner);

        $releases = $this->getJson('/api/v1/releases')->assertOk()->json('data');
        $this->assertNotEmpty($releases);
        $this->assertSame((string) config('workintel.agent.latest_version'), $releases[0]['version']);
        $this->get('/api/v1/releases/'.$releases[0]['slug'].'/download')->assertOk();
    }
}
