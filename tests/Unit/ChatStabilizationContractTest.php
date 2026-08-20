<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Protects Chat V2.1 stabilization contracts that can be verified without a database.
 */
class ChatStabilizationContractTest extends TestCase
{
    /**
     * Ensures the backend excludes self/inactive users and rejects explicit self-DMs.
     */
    public function test_backend_member_picker_and_self_dm_guards_are_present(): void
    {
        $service = file_get_contents(base_path('app/Services/Chat/ChatService.php'));
        $this->assertStringContainsString('where(\'id\', \'!=\', $member->id)', $service);
        $this->assertStringContainsString('whereHas(\'user\', fn ($query) => $query->where(\'status\', \'active\'))', $service);
        $this->assertStringContainsString('SELF_CONVERSATION_NOT_ALLOWED', $service);
        $this->assertStringContainsString('where(\'direct_key\', $directKey)', $service);
    }

    /**
     * Ensures the client receives and maps the current workspace member identifier.
     */
    public function test_auth_and_chat_payloads_expose_current_member_identity(): void
    {
        $types = file_get_contents(base_path('resources/js/auth/types.ts'));
        $auth = file_get_contents(base_path('resources/js/auth/authService.ts'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/V1/ChatController.php'));
        $this->assertStringContainsString('memberId?: number', $types);
        $this->assertStringContainsString('memberId: workspace.member_id', $auth);
        $this->assertStringContainsString("'viewer_member_id' => \$member->id", $controller);
    }

    /**
     * Ensures responsive, unread and RTL-safe chat stabilization hooks remain in source.
     */
    public function test_chat_ui_contains_stabilized_responsive_and_scroll_contracts(): void
    {
        $page = file_get_contents(base_path('resources/js/pages/Chat.tsx'));
        $css = file_get_contents(base_path('resources/css/app.css'));
        foreach (['chat-mobile-', 'Jump to latest', 'chat-unread-divider', 'nearBottom', 'announceTyping', 'currentMemberId'] as $needle) {
            $this->assertStringContainsString($needle, $page);
        }
        $this->assertStringContainsString('border-inline-start:3px solid var(--warning)', $css);
        $this->assertStringContainsString('html[dir="rtl"] .chat-mobile-back svg', $css);
        $this->assertStringContainsString('@media(max-width:760px)', $css);
        $this->assertStringNotContainsString('box-shadow:inset 3px 0 0 var(--warning)', $css);
    }
}
