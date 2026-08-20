<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Provides auth flow test behavior within the WorkIntel application. */ class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test seeded owner can sign in and read the current user operation for the current WorkIntel workflow. */ public function test_seeded_owner_can_sign_in_and_read_the_current_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
        ])->postJson('/api/v1/auth/login', [
            'email' => 'owner@acme.test',
            'password' => 'password',
        ])->assertOk()
          ->assertJsonPath('user.email', 'owner@acme.test');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'owner@acme.test');
    }

    /** Handles the test registration creates owner permissions without demo seeding operation for the current WorkIntel workflow. */ public function test_registration_creates_owner_permissions_without_demo_seeding(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
        ])->postJson('/api/v1/auth/register', [
            'first_name' => 'New',
            'last_name' => 'Owner',
            'email' => 'new-owner@example.test',
            'company_name' => 'New Company',
            'password' => 'SecureOwner#123',
            'timezone' => 'Asia/Karachi',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'new-owner@example.test');

        $user = User::where('email', 'new-owner@example.test')->firstOrFail();
        $membership = $user->memberships()->with('roles.permissions')->firstOrFail();

        $this->assertSame('owner', $membership->roles->firstOrFail()->slug);
        $this->assertGreaterThan(0, $membership->roles->firstOrFail()->permissions->count());
        $this->assertNotNull($membership->job_title_id);
        $this->assertDatabaseHas('job_titles', [
            'id' => $membership->job_title_id,
            'workspace_id' => $membership->workspace_id,
            'name' => 'Workspace Owner',
        ]);

        foreach (['owner', 'admin', 'hr', 'manager', 'team-lead', 'payroll-manager', 'employee', 'client'] as $roleSlug) {
            $this->assertDatabaseHas('roles', [
                'workspace_id' => $membership->workspace_id,
                'slug' => $roleSlug,
            ]);
        }
    }
}
