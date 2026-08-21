<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Protects M10 Chat & Collaboration V4 inbox, context and internal-resource contracts without a database. */
class ChatCollaborationV4ContractTest extends TestCase
{
    /** Verifies the V4 routes and backend collaboration contracts stay wired. */
    public function test_v4_inbox_context_and_resource_contracts_are_present(): void
    {
        $routes=(string)file_get_contents(base_path('routes/chat.php'));
        $service=(string)file_get_contents(base_path('app/Services/Chat/ChatService.php'));
        $collab=(string)file_get_contents(base_path('app/Services/Chat/ChatWorkspaceCollaborationService.php'));
        foreach(['/inbox','/context','/save-note'] as $token)$this->assertStringContainsString($token,$routes);
        foreach(['collaborationInbox','conversationContext','updateSavedNote'] as $token)$this->assertStringContainsString($token,$service);
        foreach(['GeneratedDocument','canViewProject','canViewTask'] as $token)$this->assertStringContainsString($token,$collab);
    }

    /** Verifies the V4 UI exposes activity, context, typed resources and shared Media DAM attachment flow. */
    public function test_v4_ui_and_shared_media_contracts_are_present(): void
    {
        $page=$this->chatUiSource();
        foreach(['Collaboration Activity','Pinned messages','Your bookmarks','Recent files','Generated document','MediaFileField'] as $token)$this->assertStringContainsString($token,$page);
        $this->assertStringNotContainsString('window.prompt(', $page);
    }

    /** Verifies M10 closure triage, notification matrix, pagination, bulk context and entity-card contracts. */
    public function test_v4_closure_contracts_are_present(): void
    {
        $routes=(string)file_get_contents(base_path('routes/chat.php'));
        $service=(string)file_get_contents(base_path('app/Services/Chat/ChatService.php'));
        $collab=(string)file_get_contents(base_path('app/Services/Chat/ChatWorkspaceCollaborationService.php'));
        $page=$this->chatUiSource();
        $migration=(string)file_get_contents(base_path('database/migrations/2026_08_20_001200_create_chat_activity_states.php'));
        foreach(['/inbox/triage','/notification-preferences','/context/bulk'] as $token)$this->assertStringContainsString($token,$routes);
        foreach(['triageInbox','chatNotificationPreferences','bulkContext','pin_next','bookmark_next','file_next'] as $token)$this->assertStringContainsString($token,$service);
        foreach(['resourcePayload','available','due_at'] as $token)$this->assertStringContainsString($token,$collab);
        foreach(['Mark all done','Chat notification preferences','Load older context','Clear visible'] as $token)$this->assertStringContainsString($token,$page);
        $this->assertStringContainsString('chat_activity_states',$migration);
    }

    /** Returns the decomposed M10 chat UI source as one contract surface. */
    private function chatUiSource(): string
    {
        return implode("\n", [
            (string)file_get_contents(base_path('resources/js/pages/Chat.tsx')),
            (string)file_get_contents(base_path('resources/js/components/chat/ChatPanels.tsx')),
            (string)file_get_contents(base_path('resources/js/components/chat/chatUtils.ts')),
            (string)file_get_contents(base_path('resources/js/components/chat/chatTypes.ts')),
        ]);
    }
}
