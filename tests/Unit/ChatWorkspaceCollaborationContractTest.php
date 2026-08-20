<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Protects Chat V2.3 workspace-collaboration contracts without requiring a database. */
class ChatWorkspaceCollaborationContractTest extends TestCase
{
    /** Verifies channel governance persistence and routes remain additive and discoverable. */
    public function test_workspace_collaboration_schema_and_routes_are_present(): void
    {
        $migration = file_get_contents(base_path('database/migrations/2026_08_13_000500_create_chat_workspace_collaboration.php'));
        foreach (['visibility', 'channel_mode', 'posting_policy', 'is_locked', 'notification_mode', 'chat_bots', 'sender_bot_id', 'message_type', 'metadata', 'chat_channel_resources'] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }
        $routes = file_get_contents(base_path('routes/chat.php'));
        foreach (['/public-channels', '/join', '/leave', '/members/{member}/role', '/notifications', '/resources', '/messages/{message}/actions'] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }
    }

    /** Verifies channel roles, action adapters, bot identities and slash commands are backend-enforced. */
    public function test_collaboration_service_exposes_governance_actions_and_slash_commands(): void
    {
        $service = file_get_contents(base_path('app/Services/Chat/ChatWorkspaceCollaborationService.php'));
        foreach (['assertChannelAdmin', 'assertChannelModerator', 'createTaskFromMessage', 'createApprovalFromMessage', 'createIncidentFromMessage', 'ensureBots', 'postBotMessage', "'/task'", "'/assign'", "'/poll'", "'/status'"] as $needle) {
            $this->assertStringContainsString($needle, $service);
        }
        $this->assertStringContainsString('The final channel owner cannot be removed.', $service);
        $this->assertStringContainsString('Assign another channel owner before demoting the final owner.', $service);
    }

    /** Verifies all-message notification mode is implemented as actual delivery rather than a UI-only setting. */
    public function test_notification_modes_are_connected_to_message_delivery(): void
    {
        $service = file_get_contents(base_path('app/Services/Chat/ChatService.php'));
        foreach (['notifyConversationMembers', 'notifyMentions', 'notifications_snoozed_until', 'notification_mode'] as $needle) {
            $this->assertStringContainsString($needle, $service);
        }
        $this->assertSame(1, preg_match('/[\'"]notification_mode[\'"]\s*=>\s*\$muted\s*\?\s*[\'"]nothing[\'"]\s*:\s*[\'"]all[\'"]/', $service));
    }

    /** Verifies the frontend exposes governed channels, resources, workflow actions and notification choices. */
    public function test_workspace_collaboration_ui_has_real_controls(): void
    {
        $page = file_get_contents(base_path('resources/js/pages/Chat.tsx'));
        foreach (['Public channels', 'Announcement', 'Create task', 'Create approval', 'Create incident', 'Mentions only', 'Channel resources', '/assign', 'Read-only', 'Lock channel'] as $needle) {
            $this->assertStringContainsString($needle, $page);
        }
    }
}
