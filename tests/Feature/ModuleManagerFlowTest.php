<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceModule;
use App\Services\Modules\WorkspaceModuleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides p4 module manager flow test behavior within the WorkIntel application. */ class ModuleManagerFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the set up operation for the current WorkIntel workflow. */ protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Handles the test owner can disable tasks and existing task data is preserved while api is blocked operation for the current WorkIntel workflow. */ public function test_owner_can_disable_tasks_and_existing_task_data_is_preserved_while_api_is_blocked(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);
        $taskCount = Task::query()->where('workspace_id', $member->workspace_id)->count();

        $this->getJson('/api/v1/modules', $headers)->assertOk()->assertJsonPath('can_manage', true);
        $this->patchJson('/api/v1/modules/tasks', ['is_enabled' => false], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_enabled', false);

        $this->getJson('/api/v1/tasks', $headers)
            ->assertStatus(423)
            ->assertJsonPath('code', 'WORKSPACE_MODULE_DISABLED');
        $this->assertSame($taskCount, Task::query()->where('workspace_id', $member->workspace_id)->count());
    }

    /** Handles the test dependency guard prevents projects disable without cascade and cascade disables dependents operation for the current WorkIntel workflow. */ public function test_dependency_guard_prevents_projects_disable_without_cascade_and_cascade_disables_dependents(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);

        $this->patchJson('/api/v1/modules/projects', ['is_enabled' => false, 'cascade_dependents' => false], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('module');

        $this->patchJson('/api/v1/modules/projects', ['is_enabled' => false, 'cascade_dependents' => true], $headers)->assertOk();
        $rows = WorkspaceModule::query()->where('workspace_id', $member->workspace_id)->get()->keyBy('module_key');
        $this->assertFalse((bool) $rows['projects']->is_enabled);
        $this->assertFalse((bool) $rows['tasks']->is_enabled);
        $this->assertFalse((bool) $rows['time']->is_enabled);
    }

    /** Handles the test enabling task auto enables required project dependency operation for the current WorkIntel workflow. */ public function test_enabling_task_auto_enables_required_project_dependency(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);
        $service = app(WorkspaceModuleService::class);
        $service->update($member->workspace, 'tasks', ['is_enabled' => false], $member);
        $service->update($member->workspace, 'projects', ['is_enabled' => false, 'cascade_dependents' => true], $member);

        $this->patchJson('/api/v1/modules/tasks', ['is_enabled' => true], $headers)->assertOk();
        $this->assertTrue((bool) WorkspaceModule::where('workspace_id', $member->workspace_id)->where('module_key', 'projects')->value('is_enabled'));
        $this->assertTrue((bool) WorkspaceModule::where('workspace_id', $member->workspace_id)->where('module_key', 'tasks')->value('is_enabled'));
    }

    /** Handles the test admin can view but only owner can toggle workspace modules operation for the current WorkIntel workflow. */ public function test_admin_can_view_but_only_owner_can_toggle_workspace_modules(): void
    {
        [$admin, $member] = $this->userAndMember('admin@acme.test');
        Sanctum::actingAs($admin);
        $headers = $this->headers($member->workspace_id);
        $this->getJson('/api/v1/modules', $headers)->assertOk()->assertJsonPath('can_manage', false);
        $this->patchJson('/api/v1/modules/tasks', ['is_enabled' => false], $headers)->assertForbidden();
    }

    /** Handles the test auth payload exposes effective module state operation for the current WorkIntel workflow. */ public function test_auth_payload_exposes_effective_module_state(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);
        $this->patchJson('/api/v1/modules/tasks', ['is_enabled' => false], $headers)->assertOk();
        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.workspaces.0.modules.tasks.workspace_enabled', false)
            ->assertJsonPath('user.workspaces.0.modules.tasks.enabled', false);
    }

    /** Handles the user and member operation for the current WorkIntel workflow. */ private function userAndMember(string $email): array
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        $member = $user->memberships()->with('workspace')->where('status', 'active')->firstOrFail();
        return [$user, $member];
    }

    /** Handles the headers operation for the current WorkIntel workflow. */ private function headers(int $workspaceId): array
    {
        return ['X-Workspace-Id' => (string) $workspaceId];
    }
}
