<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/** Verifies Issue 70 in-process identity diagnostics fail closed before role mutation. */
class AccessControlSeederDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    /** Ensure an impossible demo-owner collision emits the structured identity precondition evidence. */
    public function test_coordinator_owner_collision_fails_with_identity_snapshot(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->where('slug', 'acme-corp')->firstOrFail();
        $coordinator = User::query()->where('email', 'coordinator@acme.test')->firstOrFail();
        $workspace->update(['owner_id' => $coordinator->id]);

        try {
            $this->seed(AccessControlSeeder::class);
            $this->fail('AccessControlSeeder should reject a coordinator/owner identity collision.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('AccessControlSeeder identity precondition failed:', $exception->getMessage());
            $this->assertStringContainsString('"workspace_owner_id":'.$coordinator->id, $exception->getMessage());
            $this->assertStringContainsString('"coordinator_model_id":'.$coordinator->id, $exception->getMessage());
        }

        $member = $coordinator->memberships()->where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertSame(['project-coordinator'], $member->roles()->orderBy('roles.slug')->pluck('roles.slug')->all());
    }
}
