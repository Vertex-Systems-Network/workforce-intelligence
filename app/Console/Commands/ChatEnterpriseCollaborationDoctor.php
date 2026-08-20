<?php

namespace App\Console\Commands;

use App\Support\PermissionCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Validates Chat V2.4 enterprise collaboration schema, permissions, routes and client contracts. */
class ChatEnterpriseCollaborationDoctor extends Command
{
    protected $signature = 'workintel:chat-v2.4-doctor';
    protected $description = 'Validate Chat V2.4 guests, retention, legal holds, exports, DLP and moderation controls.';

    /** Runs additive schema and source-level enterprise collaboration readiness checks. */
    public function handle(): int
    {
        $errors = [];
        foreach (['chat_legal_holds', 'chat_moderation_events', 'chat_export_jobs', 'chat_dlp_policies', 'chat_dlp_events'] as $table) {
            if (! Schema::hasTable($table)) $errors[] = "Missing {$table}.";
        }
        foreach (['collaboration_type', 'external_company', 'external_expires_at', 'external_scope'] as $column) {
            if (! Schema::hasColumn('workspace_members', $column)) $errors[] = "Missing workspace_members.{$column}.";
        }
        foreach (['external_access', 'retention_days', 'legal_hold', 'export_policy', 'dlp_mode'] as $column) {
            if (! Schema::hasColumn('chat_conversations', $column)) $errors[] = "Missing chat_conversations.{$column}.";
        }
        foreach (['security_status', 'security_reason'] as $column) {
            if (! Schema::hasColumn('chat_message_attachments', $column)) $errors[] = "Missing chat_message_attachments.{$column}.";
        }
        foreach (['chat.guests_manage', 'chat.retention_manage', 'chat.export', 'chat.legal_hold_manage', 'chat.dlp_manage'] as $slug) {
            if (! collect(PermissionCatalog::ITEMS)->contains(fn ($item) => $item[1] === $slug)) $errors[] = "Missing permission {$slug}.";
        }

        $routes = is_file(base_path('routes/chat.php')) ? file_get_contents(base_path('routes/chat.php')) : '';
        foreach (['external-invitations', 'legal-holds', '/exports', 'dlp-policies', '/moderate'] as $needle) if (! str_contains($routes, $needle)) $errors[] = "Missing route contract {$needle}.";
        $page = is_file(base_path('resources/js/pages/Chat.tsx')) ? file_get_contents(base_path('resources/js/pages/Chat.tsx')) : '';
        $enterprisePage = is_file(base_path('resources/js/components/chat/EnterpriseControls.tsx')) ? file_get_contents(base_path('resources/js/components/chat/EnterpriseControls.tsx')) : '';
        $uiSource = $page."\n".$enterprisePage;
        foreach (['Enterprise controls', 'External access', 'Legal hold', 'eDiscovery', 'DLP'] as $needle) if (! str_contains($uiSource, $needle)) $errors[] = "Missing UI contract {$needle}.";
        $console = is_file(base_path('routes/console.php')) ? file_get_contents(base_path('routes/console.php')) : '';
        if (! str_contains($console, 'workintel:chat-enterprise-maintenance')) $errors[] = 'Missing Chat V2.4 maintenance schedule.';

        if ($errors) {
            foreach ($errors as $error) $this->error($error);
            return self::FAILURE;
        }
        $this->info('Chat V2.4 enterprise collaboration: PASS');
        return self::SUCCESS;
    }
}
