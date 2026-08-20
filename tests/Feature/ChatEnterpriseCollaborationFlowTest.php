<?php

namespace Tests\Feature;

use App\Models\ChatDlpEvent;
use App\Models\ChatMessage;
use App\Models\ChatMessageEditHistory;
use App\Models\ChatModerationEvent;
use App\Models\Permission;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Services\Chat\ChatEnterpriseMaintenanceService;
use App\Services\Identity\WorkspaceRegistrationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Exercises Chat V2.4 external collaboration, legal hold, DLP, eDiscovery and moderation flows. */
class ChatEnterpriseCollaborationFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seeds the standard workspace and isolates outbound notifications before every enterprise chat test. */
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    /** Verifies external invitations are one-conversation, expiring and restricted from ordinary workspace chat creation. */
    public function test_external_guest_is_invited_to_one_conversation_and_cannot_create_workspace_conversations(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        $headers = $this->headers($ownerMember->workspace_id);
        Sanctum::actingAs($owner);

        $channel = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'channel', 'name' => 'Vendor Delivery Room', 'visibility' => 'private',
        ], $headers)->assertCreated()->json('data');
        $this->putJson("/api/v1/chat/enterprise/conversations/{$channel['id']}/policy", ['external_access' => true], $headers)
            ->assertOk()->assertJsonPath('data.external_access', true);

        $inviteResponse = $this->postJson("/api/v1/chat/enterprise/conversations/{$channel['id']}/external-invitations", [
            'email' => 'external.vendor@example.test', 'collaboration_type' => 'vendor', 'external_company' => 'Vendor Co',
            'external_expires_at' => now()->addDays(30)->toIso8601String(),
        ], $headers)->assertCreated();
        $token = $inviteResponse->json('token');
        $invite = WorkspaceInvitation::findOrFail($inviteResponse->json('data.id'));
        $this->assertSame(hash('sha256', $token), $invite->token_hash);
        $this->assertNotSame($token, $invite->token_hash);
        $this->assertSame($channel['id'], $invite->chat_conversation_id);

        $member = app(WorkspaceRegistrationService::class)->acceptInvitation($invite->load('workspace'), [
            'first_name' => 'External', 'last_name' => 'Vendor', 'email' => 'external.vendor@example.test',
            'password' => 'StrongPass123!', 'timezone' => 'UTC',
        ]);
        $this->assertSame('vendor', $member->collaboration_type);
        $this->assertSame('Vendor Co', $member->external_company);
        $this->assertSame(1, DB::table('chat_conversation_members')->where('member_id', $member->id)->count());
        $this->assertTrue(DB::table('chat_conversation_members')->where(['member_id' => $member->id, 'conversation_id' => $channel['id']])->exists());

        $external = User::where('email', 'external.vendor@example.test')->firstOrFail();
        Sanctum::actingAs($external);
        $this->getJson('/api/v1/chat/options', $headers)->assertOk()
            ->assertJsonPath('data.is_external', true)
            ->assertJsonCount(0, 'data.projects')
            ->assertJsonCount(0, 'data.tasks');
        $this->getJson('/api/v1/chat/public-channels', $headers)->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/chat/conversations', ['type' => 'group', 'name' => 'Not allowed', 'member_ids' => [$ownerMember->id]], $headers)
            ->assertForbidden();
    }

    /** Verifies per-field enterprise policy authorization cannot be bypassed through a mixed policy endpoint. */
    public function test_enterprise_conversation_policy_enforces_field_specific_permissions(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        $headers = $this->headers($ownerMember->workspace_id);
        Sanctum::actingAs($owner);
        $channel = $this->postJson('/api/v1/chat/conversations', ['type' => 'channel', 'name' => 'Scoped Governance', 'member_ids' => [$employeeMember->id]], $headers)->assertCreated()->json('data');

        $exportPermission = Permission::where('slug', 'chat.export')->firstOrFail();
        $employeeMember->roles()->firstOrFail()->permissions()->syncWithoutDetaching([$exportPermission->id]);
        Sanctum::actingAs($employee);
        $this->putJson("/api/v1/chat/enterprise/conversations/{$channel['id']}/policy", ['external_access' => true], $headers)->assertForbidden();
        $this->putJson("/api/v1/chat/enterprise/conversations/{$channel['id']}/policy", ['export_policy' => 'members'], $headers)
            ->assertOk()->assertJsonPath('data.export_policy', 'members');
    }

    /** Verifies legal hold overrides retention cleanup until the hold is explicitly released. */
    public function test_legal_hold_prevents_retention_purge_until_release(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        $headers = $this->headers($ownerMember->workspace_id);
        Sanctum::actingAs($owner);
        $channel = $this->postJson('/api/v1/chat/conversations', ['type' => 'channel', 'name' => 'Investigation'], $headers)->assertCreated()->json('data');
        $this->putJson("/api/v1/chat/enterprise/conversations/{$channel['id']}/policy", ['retention_days' => 1], $headers)->assertOk();
        $message = $this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages", ['body' => 'Preserve this evidence'], $headers)->assertCreated()->json('data');
        ChatMessage::whereKey($message['id'])->update(['created_at' => now()->subDays(5)]);

        $hold = $this->postJson('/api/v1/chat/enterprise/legal-holds', ['name' => 'Case 2026-42', 'conversation_id' => $channel['id']], $headers)
            ->assertCreated()->assertJsonPath('data.status', 'active')->json('data');
        $result = app(ChatEnterpriseMaintenanceService::class)->run($ownerMember->workspace_id);
        $this->assertGreaterThanOrEqual(1, $result['held_conversations']);
        $this->assertTrue(ChatMessage::whereKey($message['id'])->exists());

        $this->postJson("/api/v1/chat/enterprise/legal-holds/{$hold['id']}/release", [], $headers)->assertOk()->assertJsonPath('data.status', 'released');
        app(ChatEnterpriseMaintenanceService::class)->run($ownerMember->workspace_id);
        $this->assertFalse(ChatMessage::whereKey($message['id'])->exists());
    }

    /** Verifies blocking and quarantine DLP policies remain authoritative on the server. */
    public function test_dlp_blocks_sensitive_text_and_quarantines_matching_attachments(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        [, $employeeMember] = $this->userAndMember('employee@acme.test');
        $headers = $this->headers($ownerMember->workspace_id);
        Sanctum::actingAs($owner);
        $channel = $this->postJson('/api/v1/chat/conversations', ['type' => 'channel', 'name' => 'DLP Room', 'member_ids' => [$employeeMember->id]], $headers)->assertCreated()->json('data');

        $this->postJson('/api/v1/chat/enterprise/dlp-policies', [
            'name' => 'Secrets', 'mode' => 'block', 'keywords' => ['top-secret'], 'active' => true,
        ], $headers)->assertCreated();
        $this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages", ['body' => 'This is top-secret material'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->assertTrue(ChatDlpEvent::where('conversation_id', $channel['id'])->where('action', 'blocked')->exists());

        $this->postJson('/api/v1/chat/enterprise/dlp-policies', [
            'name' => 'Executable quarantine', 'mode' => 'quarantine', 'file_extensions' => ['exe'], 'active' => true,
        ], $headers)->assertCreated();
        $upload = UploadedFile::fake()->create('unsafe.exe', 8, 'application/octet-stream');
        $sent = $this->post("/api/v1/chat/conversations/{$channel['id']}/messages", ['body' => 'Review attachment', 'attachments' => [$upload]], $headers)
            ->assertCreated()->json('data');
        $this->assertSame('quarantined', $sent['attachments'][0]['security_status']);
        $this->assertTrue(ChatDlpEvent::where('attachment_id', $sent['attachments'][0]['id'])->where('action', 'quarantined')->exists());
    }

    /** Verifies eDiscovery output is private and moderation redaction produces an immutable audit snapshot. */
    public function test_ediscovery_and_audited_redaction_preserve_governance_boundaries(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        $headers = $this->headers($ownerMember->workspace_id);
        Sanctum::actingAs($owner);
        $channel = $this->postJson('/api/v1/chat/conversations', ['type' => 'channel', 'name' => 'eDiscovery Room'], $headers)->assertCreated()->json('data');
        $message = $this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages", ['body' => 'Governed message'], $headers)->assertCreated()->json('data');

        $exportResponse = $this->postJson("/api/v1/chat/enterprise/conversations/{$channel['id']}/exports", ['format' => 'json'], $headers)
            ->assertCreated()->assertJsonPath('data.status', 'completed');
        $export = $exportResponse->json('data');
        $this->assertArrayNotHasKey('path', $export);
        $this->assertArrayNotHasKey('disk', $export);
        $this->assertArrayNotHasKey('checksum_sha256', $export);
        $this->get("/api/v1/chat/enterprise/exports/{$export['id']}/download", $headers)->assertOk();

        $this->postJson("/api/v1/chat/enterprise/messages/{$message['id']}/moderate", ['action' => 'redact', 'reason' => 'Policy review'], $headers)
            ->assertOk()->assertJsonPath('data.action', 'redact');
        $this->assertTrue(ChatMessageEditHistory::where('message_id', $message['id'])->where('body', 'Governed message')->exists());
        $this->assertTrue(ChatModerationEvent::where('message_id', $message['id'])->where('action', 'message.redact')->exists());
        $this->assertNull(ChatMessage::findOrFail($message['id'])->body);
    }

    /** Verifies channel moderator roles can use enterprise moderation without global moderation permission. */
    public function test_channel_moderator_can_flag_and_redact_messages_without_workspace_moderation_permission(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        $headers = $this->headers($ownerMember->workspace_id);

        $this->assertFalse($employeeMember->hasPermission('chat.moderate'));
        $this->assertFalse($employeeMember->hasPermission('chat.manage'));

        Sanctum::actingAs($owner);
        $channel = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'channel', 'name' => 'Moderator Governance', 'member_ids' => [$employeeMember->id],
        ], $headers)->assertCreated()->json('data');
        $this->putJson("/api/v1/chat/conversations/{$channel['id']}/members/{$employeeMember->id}/role", ['role' => 'moderator'], $headers)
            ->assertOk();
        $flagTarget = $this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages", ['body' => 'Flag this message'], $headers)
            ->assertCreated()->json('data');
        $redactTarget = $this->postJson("/api/v1/chat/conversations/{$channel['id']}/messages", ['body' => 'Redact this message'], $headers)
            ->assertCreated()->json('data');

        Sanctum::actingAs($employee);
        $this->postJson("/api/v1/chat/enterprise/messages/{$flagTarget['id']}/moderate", ['action' => 'flag', 'reason' => 'Moderator review'], $headers)
            ->assertOk()->assertJsonPath('data.action', 'flag');
        $this->postJson("/api/v1/chat/enterprise/messages/{$redactTarget['id']}/moderate", ['action' => 'redact', 'reason' => 'Channel policy'], $headers)
            ->assertOk()->assertJsonPath('data.action', 'redact');

        $this->assertTrue(ChatModerationEvent::where('message_id', $flagTarget['id'])->where('actor_member_id', $employeeMember->id)->where('action', 'message.flag')->exists());
        $this->assertTrue(ChatModerationEvent::where('message_id', $redactTarget['id'])->where('actor_member_id', $employeeMember->id)->where('action', 'message.redact')->exists());
        $this->assertNotNull(data_get(ChatMessage::findOrFail($flagTarget['id'])->metadata, 'moderation.flagged_at'));
        $this->assertNull(ChatMessage::findOrFail($redactTarget['id'])->body);
    }

    /** Resolves a seeded user and its active workspace membership. */
    private function userAndMember(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();
        $member = $user->memberships()->with('workspace')->where('status', 'active')->firstOrFail();
        return [$user, $member];
    }

    /** Builds the workspace header used by authenticated API tests. */
    private function headers(int $workspaceId): array
    {
        return ['X-Workspace-Id' => (string) $workspaceId];
    }
}
