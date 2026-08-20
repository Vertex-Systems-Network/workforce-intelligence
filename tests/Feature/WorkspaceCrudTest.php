<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides workspace crud test behavior within the WorkIntel application. */ class WorkspaceCrudTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test owner can manage people clients projects and tasks operation for the current WorkIntel workflow. */ public function test_owner_can_manage_people_clients_projects_and_tasks(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);

        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $person = $this->postJson('/api/v1/people', [
            'first_name' => 'Nadia',
            'last_name' => 'Khan',
            'email' => 'nadia@example.test',
            'password' => 'password123',
            'job_title' => 'QA Engineer',
            'role_slug' => 'employee',
            'employment_type' => 'full_time',
            'status' => 'active',
        ], $headers)->assertCreated()->json('data');

        $client = $this->postJson('/api/v1/clients', [
            'name' => 'Northwind',
            'company_name' => 'Northwind Ltd.',
            'email' => 'billing@northwind.test',
            'currency' => 'USD',
            'billing_rate' => 145,
            'status' => 'active',
        ], $headers)->assertCreated()->json('data');

        $project = $this->postJson('/api/v1/projects', [
            'client_id' => $client['id'],
            'name' => 'Northwind Portal',
            'code' => 'NWP',
            'status' => 'active',
            'priority' => 'high',
            'budget_type' => 'hours',
            'budget_amount' => 120,
            'estimated_minutes' => 7200,
            'billable' => true,
            'currency' => 'USD',
            'member_ids' => [$person['id']],
        ], $headers)->assertCreated()->json('data');

        $task = $this->postJson('/api/v1/tasks', [
            'project_id' => $project['id'],
            'title' => 'Build account dashboard',
            'status' => 'todo',
            'priority' => 'high',
            'estimated_minutes' => 480,
            'billable' => true,
            'assignee_ids' => [$person['id']],
        ], $headers)->assertCreated()->json('data');

        $this->assertDatabaseHas('workspace_members', ['id' => $person['id'], 'workspace_id' => $membership->workspace_id]);
        $this->assertDatabaseHas('clients', ['id' => $client['id'], 'workspace_id' => $membership->workspace_id]);
        $this->assertDatabaseHas('projects', ['id' => $project['id'], 'client_id' => $client['id']]);
        $this->assertDatabaseHas('tasks', ['id' => $task['id'], 'project_id' => $project['id']]);
        $this->assertDatabaseHas('task_assignees', ['task_id' => $task['id'], 'member_id' => $person['id']]);

        $this->putJson('/api/v1/tasks/'.$task['id'], [
            'project_id' => $project['id'],
            'title' => 'Build account dashboard v2',
            'status' => 'in_progress',
            'priority' => 'critical',
            'estimated_minutes' => 540,
            'billable' => true,
            'assignee_ids' => [$person['id']],
        ], $headers)->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->deleteJson('/api/v1/clients/'.$client['id'], [], $headers)->assertOk();
        $this->assertSame('archived', Client::findOrFail($client['id'])->status);
        $this->assertSame('Northwind Portal', Project::findOrFail($project['id'])->name);
        $this->assertSame('Build account dashboard v2', Task::findOrFail($task['id'])->title);
    }
}
