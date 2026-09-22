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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AccessControlSeeder identity precondition failed:');

        $this->seed(AccessControlSeeder::class);
    }
}
