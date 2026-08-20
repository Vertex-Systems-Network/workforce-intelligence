<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Exercises Chat V2.3 governed channels, notifications, workspace actions and slash commands. */
class ChatWorkspaceCollaborationFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seeds the standard WorkIntel workspace before each collaboration flow. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Verifies public discovery, announcement posting rules and channel-role governance. */
    public function test_public_announcement_channels_enforce_membership_and_channel_roles(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        $headers = $this->headers($ownerMember->workspace_id);

        Sanctum::actingAs($owner);
        $channel = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'channel', 'name' => 'Company News', 'visibility' => 'public', 'channel_mode' => 'announcement',
        ], $headers)->assertCreated()->assertJsonPath('data.posting_policy', 'admins')->json('data');

        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/chat/public-channels', $headers)
            ->assertOk()->assertJsonFragment(['id' => $channel['id'], 'name' => 'Company News']);
        $this->postJson("/api/v1/chat/conversations/{$channel['id']}/join", [], $headers)
            ->assertOk()->assertJsonPath('data.viewer_role', 'member');
        $this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages", ['body' => 'Members cannot announce'], $headers)
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/chat/conversations/{$channel['id']}/members/{$employeeMember->id}/role", ['role' => 'admin'], $headers)
            ->assertOk();

        // Channel role is sufficient even though the employee does not hold workspace chat.manage.
        Sanctum::actingAs($employee);
        $this->putJson("/api/v1/chat/conversations/{$channel['id']}/channel", ['is_locked' => true], $headers)
            ->assertOk()->assertJsonPath('data.is_locked', true);
        $this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages", ['body' => 'Authorized announcement'], $headers)
            ->assertCreated();

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/chat/conversations/{$channel['id']}/members/{$ownerMember->id}/role", ['role' => 'member'], $headers)
            ->assertStatus(422);
    }

    /** Verifies all, mentions-only and nothing notification modes affect actual message delivery. */
    public function test_conversation_notification_modes_control_delivery(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        $headers = $this->headers($ownerMember->workspace_id);

        Sanctum::actingAs($owner);
        $channel = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'channel', 'name' => 'Operations', 'member_ids' => [$employeeMember->id],
        ], $headers)->assertCreated()->json('data');
        $this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages", ['body' => 'All-message notification'], $headers)->assertCreated();
        $this->assertTrue(WorkspaceNotification::where('user_id', $employee->id)->where('type', 'chat.message')->exists());

        WorkspaceNotification::where('user_id', $employee->id)->delete();
        Sanctum::actingAs($employee);
        $this->putJson("/api/v1/chat/conversations/{$channel['id']}/notifications", ['mode' => 'mentions'], $headers)
            ->assertOk()->assertJsonPath('data.mode', 'mentions');

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages", ['body' => 'No generic notification'], $headers)->assertCreated();
        $this->assertFalse(WorkspaceNotification::where('user_id', $employee->id)->where('type', 'chat.message')->exists());
        $this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages", ['body' => "Review this @[member:{$employeeMember->id}]"], $headers)->assertCreated();
        $this->assertTrue(WorkspaceNotification::where('user_id', $employee->id)->where('type', 'chat.mention')->exists());

        WorkspaceNotification::where('user_id', $employee->id)->delete();
        Sanctum::actingAs($employee);
        $this->putJson("/api/v1/chat/conversations/{$channel['id']}/notifications", ['mode' => 'nothing'], $headers)
            ->assertOk()->assertJsonPath('data.muted', true);
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages", ['body' => "Muted mention @[member:{$employeeMember->id}]"], $headers)->assertCreated();
        $this->assertFalse(WorkspaceNotification::where('user_id', $employee->id)->where('type', 'chat.mention')->exists());
    }

    /** Verifies moderators can pin resources while read-only members remain unable to post. */
    public function test_channel_resources_and_read_only_roles_are_enforced(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        $headers = $this->headers($ownerMember->workspace_id);
        Sanctum::actingAs($owner);
        $channel = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'channel', 'name' => 'Runbooks', 'member_ids' => [$employeeMember->id],
        ], $headers)->assertCreated()->json('data');

        $this->putJson("/api/v1/chat/conversations/{$channel['id']}/members/{$employeeMember->id}/role", ['role' => 'moderator'], $headers)->assertOk();
        Sanctum::actingAs($employee);
        $resource = $this->postJson("/api/v1/chat/conversations/{$channel['id']}/resources", [
            'kind' => 'link', 'label' => 'Incident Runbook', 'url' => 'https://example.test/runbook',
        ], $headers)->assertCreated()->json('data');
        $this->getJson("/api/v1/chat/conversations/{$channel['id']}/resources", $headers)
            ->assertOk()->assertJsonFragment(['id' => $resource['id'], 'label' => 'Incident Runbook']);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/chat/conversations/{$channel['id']}/members/{$employeeMember->id}/role", ['role' => 'read_only'], $headers)->assertOk();
        Sanctum::actingAs($employee);
        $this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages", ['body' => 'Should be blocked'], $headers)->assertForbidden();
    }

    /** Verifies task-linked slash commands create and assign real WorkIntel tasks. */
    public function test_task_slash_commands_create_and_assign_real_tasks(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        [, $employeeMember] = $this->userAndMember('employee@acme.test');
        $headers = $this->headers($ownerMember->workspace_id);
        Sanctum::actingAs($owner);
        $options = $this->getJson('/api/v1/chat/options', $headers)->assertOk()->json('data');
        $taskOption = $options['tasks'][0];

        $conversation = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'task', 'task_id' => $taskOption['id'], 'name' => 'Task command room', 'member_ids' => [$employeeMember->id],
        ], $headers)->assertCreated()->json('data');

        $before = Task::where('workspace_id', $ownerMember->workspace_id)->count();
        $created = $this->postJson("/api/v1/chat/conversations/{$conversation['id']}/messages", ['body' => '/task Follow up from chat'], $headers)
            ->assertCreated()->assertJsonPath('data.message_type', 'action')->json('data');
        $this->assertGreaterThan($before, Task::where('workspace_id', $ownerMember->workspace_id)->count());
        $this->assertSame('task_created', $created['metadata']['action_type']);

        $assigned = $this->postJson("/api/v1/chat/conversations/{$conversation['id']}/messages", ['body' => "/assign @[member:{$employeeMember->id}]"], $headers)
            ->assertCreated()->assertJsonPath('data.metadata.action_type', 'task_assigned')->json('data');
        $linkedTask = Task::findOrFail($assigned['metadata']['task_id']);
        $this->assertTrue($linkedTask->assignees()->where('workspace_members.id', $employeeMember->id)->exists());

        $this->postJson("/api/v1/chat/conversations/{$conversation['id']}/messages", ['body' => '/help'], $headers)
            ->assertCreated()->assertJsonPath('data.sender.kind', 'bot');
    }

    /** Resolves a seeded user and its active workspace membership. */
    private function userAndMember(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();
        $member = $user->memberships()->with('workspace')->where('status', 'active')->firstOrFail();
        return [$user, $member];
    }

    /** Builds the workspace header used by authenticated API tests. */
    private function headers(int $workspaceId): array
    {
        return ['X-Workspace-Id' => (string) $workspaceId];
    }
}
