<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Verifies page customization is isolated to the authenticated user and active workspace. */
class UserPagePreferenceFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Persist, read and reset one user's page customization without changing workspace-wide settings. */
    public function test_user_can_manage_own_page_preference(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'owner@acme.test')->firstOrFail();
        $member = WorkspaceMember::query()->where('user_id', $user->id)->where('status', 'active')->firstOrFail();
        $workspace = Workspace::findOrFail($member->workspace_id);
        $headers = ['X-Workspace-Id' => (string) $workspace->id];

        $this->actingAs($user)->putJson('/api/v1/ui/preferences/overview', [
            'settings' => [
                'density' => 'compact',
                'content_width' => 'balanced',
                'motion' => 'reduced',
                'table_density' => 'compact',
                'sticky_header' => true,
                'show_descriptions' => true,
                'visible_widgets' => ['active-now', 'project-workload'],
                'widget_layout' => [['id' => 'active-now', 'x' => 0, 'y' => 0, 'w' => 3, 'h' => 2]],
            ],
        ], $headers)->assertOk()->assertJsonPath('data.density', 'compact');

        $this->actingAs($user)->getJson('/api/v1/ui/preferences/overview', $headers)
            ->assertOk()->assertJsonPath('data.visible_widgets.0', 'active-now');

        $this->actingAs($user)->deleteJson('/api/v1/ui/preferences/overview', [], $headers)->assertOk();
        $this->actingAs($user)->getJson('/api/v1/ui/preferences/overview', $headers)->assertOk()->assertJsonPath('data', []);
    }
}
