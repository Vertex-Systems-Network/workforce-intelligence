<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Certifies permission-aware global shell discovery without cross-scope entity leakage. */
class GlobalSearchFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Ensure owners can discover workspace entities across the shell-owned search categories. */
    public function test_owner_can_discover_people_projects_tasks_and_clients(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $this->getJson('/api/v1/search?q=Ahmed&limit=4', $headers)->assertOk()
            ->assertJsonFragment(['kind' => 'person', 'title' => 'Ahmed Khan']);
        $this->getJson('/api/v1/search?q=API%20Platform&limit=4', $headers)->assertOk()
            ->assertJsonFragment(['kind' => 'project', 'title' => 'API Platform']);
        $this->getJson('/api/v1/search?q=Animation%20Timeline&limit=4', $headers)->assertOk()
            ->assertJsonFragment(['kind' => 'task', 'title' => 'Animation Timeline Editor']);
        $this->getJson('/api/v1/search?q=TechCorp&limit=4', $headers)->assertOk()
            ->assertJsonFragment(['kind' => 'client', 'title' => 'TechCorp Inc.']);
    }

    /** Ensure an employee can discover assigned work but cannot search unrelated projects or clients. */
    public function test_employee_search_respects_work_scope_and_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $employee = User::query()->where('email', 'employee@acme.test')->firstOrFail();
        $membership = $employee->memberships()->firstOrFail();
        Sanctum::actingAs($employee);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $this->getJson('/api/v1/search?q=Animation%20Timeline&limit=4', $headers)->assertOk()
            ->assertJsonFragment(['kind' => 'task', 'title' => 'Animation Timeline Editor']);

        $restrictedProject = collect($this->getJson('/api/v1/search?q=API%20Platform&limit=4', $headers)->assertOk()->json('data'));
        $this->assertFalse($restrictedProject->contains(fn (array $row): bool => $row['kind'] === 'project' && $row['title'] === 'API Platform'));

        $restrictedClient = collect($this->getJson('/api/v1/search?q=TechCorp&limit=4', $headers)->assertOk()->json('data'));
        $this->assertFalse($restrictedClient->contains(fn (array $row): bool => $row['kind'] === 'client'));
    }
}
