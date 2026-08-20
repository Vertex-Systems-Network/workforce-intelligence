<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Locks the source contracts required by Chat V2.4 enterprise collaboration. */
class ChatEnterpriseCollaborationContractTest extends TestCase
{
    /** Verifies the additive migration owns all enterprise chat schema without destructive replacement. */
    public function test_enterprise_migration_contract_is_present(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_13_000600_create_chat_enterprise_collaboration.php');
        foreach (['chat_legal_holds', 'chat_moderation_events', 'chat_export_jobs', 'chat_dlp_policies', 'chat_dlp_events'] as $table) {
            self::assertStringContainsString($table, $source);
        }
        foreach (['collaboration_type', 'external_expires_at', 'external_access', 'retention_days', 'legal_hold', 'export_policy', 'dlp_mode', 'security_status'] as $column) {
            self::assertStringContainsString($column, $source);
        }
        self::assertStringContainsString("if (! Schema::hasTable('chat_legal_holds'))", $source);
    }

    /** Verifies enterprise permissions and the restrictive external collaborator role are seeded. */
    public function test_enterprise_permissions_and_external_role_are_registered(): void
    {
        $permissions = file_get_contents(dirname(__DIR__, 2).'/app/Support/PermissionCatalog.php');
        foreach (['chat.guests_manage', 'chat.retention_manage', 'chat.export', 'chat.legal_hold_manage', 'chat.dlp_manage'] as $permission) {
            self::assertStringContainsString($permission, $permissions);
        }
        $seeder = file_get_contents(dirname(__DIR__, 2).'/database/seeders/ChatCollaborationSeeder.php');
        self::assertStringContainsString("'external-collaborator'", $seeder);
        self::assertStringContainsString("['chat.view', 'chat.create']", $seeder);
        self::assertStringContainsString("'dataset' => 'chat_messages'", $seeder);
    }

    /** Verifies DLP, eDiscovery, legal hold and maintenance contracts are wired through protected routes. */
    public function test_enterprise_routes_and_services_are_wired(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/chat.php');
        foreach (['external-invitations', 'legal-holds', '/exports', 'dlp-policies', '/moderate'] as $route) {
            self::assertStringContainsString($route, $routes);
        }
        self::assertStringContainsString("Route::post('/enterprise/messages/{message}/moderate', [ChatEnterpriseController::class, 'moderateMessage']);", $routes);
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/Chat/ChatEnterpriseCollaborationService.php');
        self::assertStringContainsString("private/chat-exports/", $service);
        self::assertStringContainsString("requested_by_member_id", $service);
        self::assertStringContainsString("chat.guests_manage", $service);
        self::assertStringContainsString("chat.retention_manage", $service);
        self::assertStringContainsString("chat.dlp_manage", $service);
        self::assertStringContainsString('canModerateConversation', $service);
        $maintenance = file_get_contents(dirname(__DIR__, 2).'/app/Services/Chat/ChatEnterpriseMaintenanceService.php');
        self::assertStringContainsString('held_conversations', $maintenance);
        self::assertStringContainsString('external_expired', $maintenance);
        self::assertStringContainsString('messages_purged', $maintenance);
    }

    /** Verifies the Chat UI exposes enterprise controls and external/DLP safety indicators. */
    public function test_enterprise_frontend_contract_is_present(): void
    {
        $chat = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/Chat.tsx');
        $enterprise = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/chat/EnterpriseControls.tsx');
        self::assertStringContainsString('EnterpriseControls', $chat);
        self::assertStringContainsString('External ·', $chat);
        self::assertStringContainsString('Quarantined', $chat);
        foreach (['Enterprise controls', 'External access', 'Legal hold', 'eDiscovery', 'DLP'] as $label) {
            self::assertStringContainsString($label, $enterprise);
        }
    }
}
