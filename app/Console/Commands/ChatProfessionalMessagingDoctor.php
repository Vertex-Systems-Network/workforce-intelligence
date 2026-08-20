<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Validates Chat V2.2 professional messaging schema and source contracts. */
class ChatProfessionalMessagingDoctor extends Command
{
    protected $signature = 'workintel:chat-v2-doctor';

    protected $description = 'Validate Chat V2 professional messaging schema, routes and client surfaces.';

    /** Runs the professional messaging readiness checks and reports actionable failures. */
    public function handle(): int
    {
        $errors = [];
        foreach (['chat_message_edit_history', 'chat_saved_messages', 'chat_drafts', 'chat_polls', 'chat_poll_options', 'chat_poll_votes', 'chat_thread_follows'] as $table) {
            if (! Schema::hasTable($table)) $errors[] = "Missing {$table}.";
        }
        if (! Schema::hasColumn('chat_messages', 'forwarded_from_message_id')) $errors[] = 'Missing chat_messages.forwarded_from_message_id.';
        $routes = base_path('routes/chat.php');
        $page = base_path('resources/js/pages/Chat.tsx');
        foreach (['/history', '/save', '/forward', '/thread', '/polls/', '/draft'] as $needle) {
            if (! is_file($routes) || ! str_contains(file_get_contents($routes), $needle)) $errors[] = "Missing chat route contract {$needle}.";
        }
        foreach (['Saved Messages', 'Edit history', 'Forward message', 'Create poll', 'Reply in thread', 'has:file'] as $needle) {
            if (! is_file($page) || ! str_contains(file_get_contents($page), $needle)) $errors[] = "Missing Chat V2 UI contract {$needle}.";
        }
        if ($errors) {
            foreach ($errors as $error) $this->error($error);
            return self::FAILURE;
        }
        $this->info('Chat V2 professional messaging: PASS');
        return self::SUCCESS;
    }
}
