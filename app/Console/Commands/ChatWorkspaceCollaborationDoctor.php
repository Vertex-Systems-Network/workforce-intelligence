<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Validates Chat V2.3 workspace collaboration schema, routes and client contracts. */
class ChatWorkspaceCollaborationDoctor extends Command
{
    protected $signature = 'workintel:chat-v2.3-doctor';
    protected $description = 'Validate Chat V2.3 channels, workspace actions, bots and notification controls.';

    /** Runs additive schema and source-level collaboration readiness checks. */
    public function handle(): int
    {
        $errors = [];
        foreach (['chat_bots', 'chat_channel_resources'] as $table) if (! Schema::hasTable($table)) $errors[] = "Missing {$table}.";
        foreach (['visibility', 'channel_mode', 'posting_policy', 'is_locked'] as $column) if (! Schema::hasColumn('chat_conversations', $column)) $errors[] = "Missing chat_conversations.{$column}.";
        foreach (['notification_mode', 'notifications_snoozed_until'] as $column) if (! Schema::hasColumn('chat_conversation_members', $column)) $errors[] = "Missing chat_conversation_members.{$column}.";
        foreach (['sender_bot_id', 'message_type', 'metadata'] as $column) if (! Schema::hasColumn('chat_messages', $column)) $errors[] = "Missing chat_messages.{$column}.";

        $routes = is_file(base_path('routes/chat.php')) ? file_get_contents(base_path('routes/chat.php')) : '';
        foreach (['/public-channels', '/join', '/channel', '/notifications', '/resources', '/actions'] as $needle) if (! str_contains($routes, $needle)) $errors[] = "Missing route contract {$needle}.";
        $page = is_file(base_path('resources/js/pages/Chat.tsx')) ? file_get_contents(base_path('resources/js/pages/Chat.tsx')) : '';
        foreach (['Public channels', 'Announcement', 'Create task', 'Create approval', 'Create incident', '/help', '/assign', 'Mentions only', 'Channel resources'] as $needle) if (! str_contains($page, $needle)) $errors[] = "Missing UI contract {$needle}.";

        if ($errors) {
            foreach ($errors as $error) $this->error($error);
            return self::FAILURE;
        }
        $this->info('Chat V2.3 workspace collaboration: PASS');
        return self::SUCCESS;
    }
}
