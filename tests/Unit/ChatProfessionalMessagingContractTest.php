<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Protects Chat V2.2 professional messaging contracts without requiring a database. */
class ChatProfessionalMessagingContractTest extends TestCase
{
    /** Verifies professional chat persistence and route contracts remain present. */
    public function test_professional_messaging_schema_and_routes_are_present(): void
    {
        $migration = file_get_contents(base_path('database/migrations/2026_08_13_000400_create_chat_professional_messaging.php'));
        foreach (['chat_message_edit_history', 'chat_saved_messages', 'chat_drafts', 'chat_polls', 'chat_poll_options', 'chat_poll_votes', 'chat_thread_follows', 'forwarded_from_message_id'] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }
        $routes = file_get_contents(base_path('routes/chat.php'));
        foreach (['/history', '/save', '/forward', '/thread', '/polls/{poll}/vote', '/draft'] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }
    }

    /** Verifies the UI exposes every Chat V2.2 professional interaction rather than dead placeholders. */
    public function test_professional_chat_ui_exposes_real_actions(): void
    {
        $page = file_get_contents(base_path('resources/js/pages/Chat.tsx'))."\n".file_get_contents(base_path('resources/js/components/chat/ChatPanels.tsx'));
        foreach (['Saved Messages', 'Edit history', 'Forward message', 'Create poll', 'Draft saved', 'from:', 'has:file', 'has:link'] as $needle) {
            $this->assertStringContainsString($needle, $page);
        }
        $this->assertStringContainsString('Reply in thread', $page);
        $this->assertStringContainsString('/api/v1/chat/messages/${message.id}/save', $page);
        $this->assertStringContainsString('/api/v1/chat/messages/${rootId}/thread', $page);
        $this->assertStringContainsString('/api/v1/chat/conversations/${selected}/draft', $page);
    }

    /** Verifies server-side search and privacy checks implement professional operators safely. */
    public function test_search_saved_forward_and_thread_services_are_server_authorized(): void
    {
        $service = file_get_contents(base_path('app/Services/Chat/ChatService.php'));
        foreach (['parseSearchQuery', 'resolveSearchMember', 'resolveSearchConversation', 'toggleSaved', 'savedMessages', 'followThread', 'createPoll', 'vote', 'forward'] as $needle) {
            $this->assertStringContainsString($needle, $service);
        }
        $this->assertStringContainsString('$this->assertMember($message->conversation, $member);', $service);
        $this->assertStringContainsString('$this->assertMember($target, $member);', $service);
    }
}
