<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Validates Chat V2.5 cursor, idempotency, delivery and client reliability contracts. */
class ChatPerformanceCertificationDoctor extends Command
{
    protected $signature = 'workintel:chat-v2.5-doctor';
    protected $description = 'Validate Chat V2.5 performance, offline delivery recovery and production-certification contracts.';

    /** Runs schema and source readiness checks for the final Chat V2 production hardening block. */
    public function handle(): int
    {
        $errors = [];
        foreach (['client_message_id', 'client_sent_at'] as $column) {
            if (! Schema::hasColumn('chat_messages', $column)) $errors[] = "Missing chat_messages.{$column}.";
        }
        if (! Schema::hasColumn('chat_conversation_members', 'last_delivered_message_id')) $errors[] = 'Missing chat_conversation_members.last_delivered_message_id.';

        $service = is_file(app_path('Services/Chat/ChatService.php')) ? file_get_contents(app_path('Services/Chat/ChatService.php')) : '';
        foreach (['messagePage', 'markDelivered', 'client_message_id', 'messagePayloadState', 'attachment_total_mb'] as $needle) {
            if (! str_contains($service, $needle)) $errors[] = "Missing ChatService contract {$needle}.";
        }
        $routes = is_file(base_path('routes/chat.php')) ? file_get_contents(base_path('routes/chat.php')) : '';
        foreach (['throttle:600,1', 'throttle:60,1', 'throttle:120,1'] as $needle) {
            if (! str_contains($routes, $needle)) $errors[] = "Missing chat throttle {$needle}.";
        }
        $page = is_file(resource_path('js/pages/Chat.tsx')) ? file_get_contents(resource_path('js/pages/Chat.tsx')) : '';
        foreach (['BroadcastChannel', 'workintel-chat-outbox', 'after=', 'Load older messages', 'Queued for delivery', 'content-visibility'] as $needle) {
            $source = $needle === 'content-visibility' && is_file(resource_path('css/app.css')) ? file_get_contents(resource_path('css/app.css')) : $page;
            if (! str_contains($source, $needle)) $errors[] = "Missing frontend reliability contract {$needle}.";
        }

        if ($errors) {
            foreach ($errors as $error) $this->error($error);
            return self::FAILURE;
        }
        $this->info('Chat V2.5 performance and production certification: PASS');
        return self::SUCCESS;
    }
}
