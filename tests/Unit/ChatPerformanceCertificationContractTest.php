<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Locks structural contracts required by Chat V2.5 without depending on whitespace formatting. */
class ChatPerformanceCertificationContractTest extends TestCase
{
    /** Verify additive idempotency and delivery schema remains upgrade-safe. */
    public function test_performance_migration_contract_is_additive(): void
    {
        $source = file_get_contents(base_path('database/migrations/2026_08_13_000700_create_chat_performance_certification.php'));
        foreach (['client_message_id', 'client_sent_at', 'last_delivered_message_id', 'chat_conv_sender_client_uq', 'chat_conv_parent_message_idx'] as $token) {
            $this->assertStringContainsString($token, $source);
        }
        $this->assertMatchesRegularExpression("/Schema::hasColumn\\(\\s*['\"]chat_messages['\"]\\s*,\\s*['\"]client_message_id['\"]\\s*\\)/", $source);
    }

    /** Verify server pagination, batch payload state, attachment safety and throttling contracts. */
    public function test_server_performance_and_safety_contracts_are_wired(): void
    {
        $service = file_get_contents(base_path('app/Services/Chat/ChatService.php'));
        foreach (['messagePage', 'messagePayloadState', 'markDelivered', 'attachment_total_mb', 'blocked_extensions', 'lockForUpdate'] as $token) {
            $this->assertStringContainsString($token, $service);
        }
        $this->assertSame(1, preg_match('/where\(\s*[\'"]id[\'"]\s*,\s*[\'"]<=[\'"]\s*,\s*\$around\s*\)/', $service));
        $routes = file_get_contents(base_path('routes/chat.php'));
        foreach (['throttle:600,1', 'throttle:60,1', 'throttle:120,1'] as $throttle) {
            $this->assertStringContainsString($throttle, $routes);
        }
    }

    /** Verify the web client owns cursor history, offline outbox, multi-tab sync and bounded rendering. */
    public function test_frontend_reliability_contracts_are_present(): void
    {
        $page = file_get_contents(base_path('resources/js/pages/Chat.tsx'));
        foreach (['BroadcastChannel', 'workintel-chat-outbox', 'createClientMessageId', 'loadOlderMessages', '?after=', 'Queued for delivery', 'retryOutboxMessage', 'mergeMessageWindow'] as $token) {
            $this->assertStringContainsString($token, $page);
        }
        $css = file_get_contents(base_path('resources/css/app.css'));
        $this->assertSame(1, preg_match('/content-visibility\s*:\s*auto/', $css));
        $this->assertStringContainsString('.chat-sync-state', $css);
    }
}
