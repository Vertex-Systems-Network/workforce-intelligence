<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Exercises idempotency, cursor pagination, delivery cursors and attachment safety for Chat V2.5. */
class ChatPerformanceCertificationFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seeds a standard workspace before every production-hardening flow. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Verifies retrying the same client message id never creates duplicate chat messages. */
    public function test_client_message_id_is_idempotent(): void
    {
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        [, $managerMember] = $this->userAndMember('manager@acme.test');
        Sanctum::actingAs($employee);
        $headers = $this->headers($employeeMember->workspace_id);
        $conversation = $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'member_ids' => [$managerMember->id]], $headers)->assertCreated()->json('data');
        $payload = ['body' => 'Exactly once please', 'client_message_id' => 'web:test-idempotent-1', 'client_sent_at' => now()->toIso8601String()];

        $first = $this->postJson("/api/v1/chat/conversations/{$conversation['id']}/messages", $payload, $headers)->assertCreated()->assertJsonPath('idempotent_replay', false)->json('data');
        $second = $this->postJson("/api/v1/chat/conversations/{$conversation['id']}/messages", $payload, $headers)->assertOk()->assertJsonPath('idempotent_replay', true)->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, ChatMessage::where('conversation_id', $conversation['id'])->where('client_message_id', 'web:test-idempotent-1')->count());
    }

    /** Verifies older and newer cursor pages remain ordered and expose explicit page metadata. */
    public function test_cursor_pagination_supports_large_histories(): void
    {
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        [, $managerMember] = $this->userAndMember('manager@acme.test');
        Sanctum::actingAs($employee);
        $headers = $this->headers($employeeMember->workspace_id);
        $conversation = $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'member_ids' => [$managerMember->id]], $headers)->assertCreated()->json('data');
        for ($index = 1; $index <= 125; $index++) {
            ChatMessage::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'workspace_id' => $employeeMember->workspace_id, 'conversation_id' => $conversation['id'], 'sender_member_id' => $employeeMember->id, 'body' => "Message {$index}"]);
        }

        $latest = $this->getJson("/api/v1/chat/conversations/{$conversation['id']}/messages?limit=60", $headers)->assertOk();
        $this->assertCount(60, $latest->json('data'));
        $this->assertTrue($latest->json('meta.has_more'));
        $oldest = (int) $latest->json('meta.oldest_id');
        $older = $this->getJson("/api/v1/chat/conversations/{$conversation['id']}/messages?before={$oldest}&limit=60", $headers)->assertOk();
        $this->assertCount(60, $older->json('data'));
        $targetId = (int) $older->json('data.20.id');
        $around = $this->getJson("/api/v1/chat/conversations/{$conversation['id']}/messages?around={$targetId}&limit=60", $headers)->assertOk();
        $this->assertTrue(collect($around->json('data'))->contains('id', $targetId));
        $lastKnown = (int) $latest->json('meta.newest_id');
        ChatMessage::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'workspace_id' => $employeeMember->workspace_id, 'conversation_id' => $conversation['id'], 'sender_member_id' => $managerMember->id, 'body' => 'Incremental message']);
        $newer = $this->getJson("/api/v1/chat/conversations/{$conversation['id']}/messages?after={$lastKnown}&limit=100", $headers)->assertOk();
        $this->assertCount(1, $newer->json('data'));
        $this->assertSame('Incremental message', $newer->json('data.0.body'));
    }

    /** Verifies delivery cursor advances independently from the read cursor. */
    public function test_fetch_advances_delivery_without_marking_read(): void
    {
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        [$manager, $managerMember] = $this->userAndMember('manager@acme.test');
        $headers = $this->headers($employeeMember->workspace_id);
        Sanctum::actingAs($employee);
        $conversation = $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'member_ids' => [$managerMember->id]], $headers)->assertCreated()->json('data');
        Sanctum::actingAs($manager);
        $message = $this->postJson("/api/v1/chat/conversations/{$conversation['id']}/messages", ['body' => 'Delivery cursor'], $headers)->assertCreated()->json('data');
        Sanctum::actingAs($employee);
        $this->getJson("/api/v1/chat/conversations/{$conversation['id']}/messages", $headers)->assertOk();
        $pivot = DB::table('chat_conversation_members')->where(['conversation_id' => $conversation['id'], 'member_id' => $employeeMember->id])->first();
        $this->assertGreaterThanOrEqual($message['id'], (int) $pivot->last_delivered_message_id);
        $this->assertLessThan($message['id'], (int) ($pivot->last_read_message_id ?? 0));
    }

    /** Verifies high-risk executable attachments are blocked unless enterprise DLP explicitly quarantines them. */
    public function test_high_risk_attachment_is_rejected_by_default(): void
    {
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        [, $managerMember] = $this->userAndMember('manager@acme.test');
        Sanctum::actingAs($employee);
        $headers = $this->headers($employeeMember->workspace_id);
        $conversation = $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'member_ids' => [$managerMember->id]], $headers)->assertCreated()->json('data');
        $this->post("/api/v1/chat/conversations/{$conversation['id']}/messages", [
            'body' => 'Unsafe binary', 'attachments' => [UploadedFile::fake()->create('payload.exe', 2, 'application/octet-stream')],
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('attachments');
    }

    /** Resolves one seeded user and active workspace membership. */
    private function userAndMember(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();
        $member = $user->memberships()->with('workspace')->where('status', 'active')->firstOrFail();
        return [$user, $member];
    }

    /** Builds workspace headers for authenticated API requests. */
    private function headers(int $workspaceId): array
    {
        return ['X-Workspace-Id' => (string) $workspaceId];
    }
}
