<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Exercises Chat V2.2 messaging features through authenticated workspace APIs. */
class ChatProfessionalMessagingFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seeds the standard WorkIntel workspace before each professional messaging flow. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Verifies edits are versioned, drafts persist and saved messages remain private to the viewer. */
    public function test_edit_history_drafts_and_saved_messages_work(): void
    {
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        [, $managerMember] = $this->userAndMember('manager@acme.test');
        Sanctum::actingAs($employee);
        $headers = $this->headers($employeeMember->workspace_id);
        $conversation = $this->directConversation($headers, $managerMember->id);

        $this->putJson("/api/v1/chat/conversations/{$conversation['id']}/draft", ['body' => 'Persistent draft'], $headers)
            ->assertOk()->assertJsonPath('data.body', 'Persistent draft');
        $this->getJson("/api/v1/chat/conversations/{$conversation['id']}/draft", $headers)
            ->assertOk()->assertJsonPath('data.body', 'Persistent draft');

        $message = $this->postJson("/api/v1/chat/conversations/{$conversation['id']}/messages", ['body' => 'First version'], $headers)
            ->assertCreated()->json('data');
        $this->putJson("/api/v1/chat/messages/{$message['id']}", ['body' => 'Second version'], $headers)
            ->assertOk()->assertJsonPath('data.body', 'Second version');
        $this->getJson("/api/v1/chat/messages/{$message['id']}/history", $headers)
            ->assertOk()->assertJsonPath('data.0.body', 'First version');

        $this->postJson("/api/v1/chat/messages/{$message['id']}/save", [], $headers)->assertOk()->assertJsonPath('saved', true);
        $this->getJson('/api/v1/chat/saved', $headers)->assertOk()->assertJsonPath('data.0.id', $message['id']);
        $this->postJson("/api/v1/chat/messages/{$message['id']}/save", [], $headers)->assertOk()->assertJsonPath('saved', false);
    }

    /** Verifies thread replies follow a root message and forwarding re-authorizes the target conversation. */
    public function test_threads_and_forwarding_work_across_visible_conversations(): void
    {
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        [, $managerMember] = $this->userAndMember('manager@acme.test');
        [, $hrMember] = $this->userAndMember('hr@acme.test');
        Sanctum::actingAs($employee);
        $headers = $this->headers($employeeMember->workspace_id);
        $source = $this->directConversation($headers, $managerMember->id);
        $target = $this->directConversation($headers, $hrMember->id);
        $root = $this->postJson("/api/v1/chat/conversations/{$source['id']}/messages", ['body' => 'Root message'], $headers)->assertCreated()->json('data');

        $this->postJson("/api/v1/chat/conversations/{$source['id']}/messages", ['body' => 'Thread reply', 'parent_id' => $root['id']], $headers)->assertCreated();
        $this->getJson("/api/v1/chat/messages/{$root['id']}/thread", $headers)
            ->assertOk()->assertJsonPath('data.replies.0.body', 'Thread reply');
        $this->putJson("/api/v1/chat/messages/{$root['id']}/thread/follow", ['following' => true], $headers)
            ->assertOk()->assertJsonPath('following', true);

        $forwarded = $this->postJson("/api/v1/chat/messages/{$root['id']}/forward", ['conversation_id' => $target['id'], 'note' => 'For HR'], $headers)
            ->assertCreated()->json('data');
        $this->assertSame($target['id'], $forwarded['conversation_id']);
        $this->assertSame($root['id'], $forwarded['forwarded']['id']);
    }

    /** Verifies poll voting and advanced search operators are scoped to the viewer's conversations. */
    public function test_polls_and_advanced_search_work(): void
    {
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        [, $managerMember] = $this->userAndMember('manager@acme.test');
        Sanctum::actingAs($employee);
        $headers = $this->headers($employeeMember->workspace_id);
        $conversation = $this->directConversation($headers, $managerMember->id);

        $pollMessage = $this->postJson("/api/v1/chat/conversations/{$conversation['id']}/polls", [
            'question' => 'Choose one', 'options' => ['Alpha', 'Beta'], 'allows_multiple' => false,
        ], $headers)->assertCreated()->json('data');
        $optionId = $pollMessage['poll']['options'][0]['id'];
        $this->postJson("/api/v1/chat/polls/{$pollMessage['poll']['id']}/vote", ['option_ids' => [$optionId]], $headers)
            ->assertOk()->assertJsonPath('data.options.0.mine', true);

        $linkMessage = $this->postJson("/api/v1/chat/conversations/{$conversation['id']}/messages", ['body' => 'Reference https://example.test/runbook'], $headers)
            ->assertCreated()->json('data');
        $query = urlencode("in:{$conversation['id']} from:{$employeeMember->id} has:link Reference");
        $response = $this->getJson("/api/v1/chat/search?q={$query}", $headers)->assertOk();
        $this->assertTrue(collect($response->json('data'))->contains(fn ($row) => $row['id'] === $linkMessage['id']));
    }

    /** Creates a direct conversation with one other active member and returns its API payload. */
    private function directConversation(array $headers, int $otherMemberId): array
    {
        return $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'member_ids' => [$otherMemberId]], $headers)
            ->assertStatus(201)->json('data');
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
