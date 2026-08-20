<?php

namespace Tests\Feature;

use App\Models\DocumentShareLink;
use App\Models\DocumentSignatureRequest;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Exercises Document Studio V4 nested rendering, workflow governance, sharing and public signing end to end. */
class DocumentStudioV4FlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seeds one complete demo workspace and isolates generated document storage. */
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    /** Verifies nested blocks, live preview, reusable components and version comparison work together. */
    public function test_owner_can_design_nested_v4_template_and_compare_versions(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);
        $template = DocumentTemplate::where('workspace_id', $member->workspace_id)->where('document_type', 'invoice')->firstOrFail();

        $schema = [
            ['id' => 'title', 'type' => 'heading', 'text' => 'Invoice {{invoice.number}}', 'level' => 1],
            ['id' => 'condition', 'type' => 'conditional', 'condition' => ['path' => 'invoice.discount_total', 'operator' => 'gte', 'value' => 0], 'children' => [
                ['id' => 'condition-text', 'type' => 'rich_text', 'html' => '<p>Client <strong>{{client.company_name}}</strong></p>'],
            ]],
            ['id' => 'formula', 'type' => 'formula', 'label' => 'Calculated total', 'expression' => 'invoice.subtotal + invoice.tax_total', 'decimals' => 2],
        ];

        $this->postJson('/api/v1/documents/templates/'.$template->id.'/live-preview', [
            'content_schema' => $schema,
            'settings' => ['studio_version' => 4, 'watermark' => ['enabled' => true, 'text' => 'PREVIEW', 'opacity' => 0.08]],
        ], $headers)->assertOk()->assertJsonPath('context.invoice.number', 'INV-2026-0001');

        $updated = $this->putJson('/api/v1/documents/templates/'.$template->id, [
            'content_schema' => $schema,
            'settings' => ['studio_version' => 4],
            'change_note' => 'V4 nested schema',
        ], $headers)->assertOk()->json('data');

        $component = $this->postJson('/api/v1/documents/components', [
            'name' => 'Invoice summary component', 'category' => 'Finance', 'content_schema' => [$schema[2]],
        ], $headers)->assertCreated()->json('data');
        $this->assertDatabaseHas('document_components', ['id' => $component['id'], 'workspace_id' => $member->workspace_id]);

        $versions = DocumentTemplate::findOrFail($template->id)->versions()->orderBy('version')->get();
        $this->assertGreaterThanOrEqual(2, $versions->count());
        $this->getJson('/api/v1/documents/templates/'.$template->id.'/versions/'.$versions->first()->id.'/compare/'.$versions->last()->id, $headers)
            ->assertOk()->assertJsonStructure(['data' => ['added', 'removed', 'changed', 'left', 'right']]);
        $this->assertSame((int) $updated['id'], (int) $template->id);
    }

    /** Verifies review, secure sharing and external signature tokens never persist in plaintext. */
    public function test_generated_document_can_be_reviewed_shared_and_signed_by_public_token(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);
        $template = DocumentTemplate::where('workspace_id', $member->workspace_id)->where('document_type', 'invoice')->firstOrFail();

        $generated = $this->postJson('/api/v1/documents/templates/'.$template->id.'/generate', [], $headers)->assertCreated()->json('data');
        $document = GeneratedDocument::findOrFail($generated['id']);
        $this->assertNotNull($document->render_context_encrypted);

        $this->postJson('/api/v1/documents/generated/'.$document->id.'/review', ['note' => 'Finance review'], $headers)->assertOk()->assertJsonPath('data.workflow_status', 'in_review');
        $this->postJson('/api/v1/documents/generated/'.$document->id.'/approve', ['note' => 'Approved'], $headers)->assertOk()->assertJsonPath('data.workflow_status', 'approved');

        $share = $this->postJson('/api/v1/documents/generated/'.$document->id.'/share', ['access_mode' => 'view', 'expires_in_days' => 7, 'max_views' => 3], $headers)->assertCreated()->json('data');
        preg_match('#/share/([^/]+)$#', $share['url'], $shareMatch);
        $shareToken = $shareMatch[1] ?? '';
        $this->assertNotSame('', $shareToken);
        $this->assertFalse(DocumentShareLink::where('token_hash', $shareToken)->exists());
        $this->assertTrue(DocumentShareLink::where('token_hash', hash('sha256', $shareToken))->exists());

        $signature = $this->postJson('/api/v1/documents/generated/'.$document->id.'/signature-requests', [
            'signer_name' => 'External Signer', 'signer_email' => 'signer@example.test', 'role_label' => 'Client', 'expires_in_days' => 7,
        ], $headers)->assertCreated()->json('data');
        preg_match('#/document-sign/([^/]+)$#', $signature['url'], $signatureMatch);
        $signatureToken = $signatureMatch[1] ?? '';
        $this->assertNotSame('', $signatureToken);
        $this->assertFalse(DocumentSignatureRequest::where('token_hash', $signatureToken)->exists());
        $this->assertTrue(DocumentSignatureRequest::where('token_hash', hash('sha256', $signatureToken))->exists());

        $this->getJson('/api/v1/public/documents/sign/'.$signatureToken)
            ->assertOk()->assertJsonPath('data.signer_name', 'External Signer')->assertJsonPath('data.direction', 'ltr');
        $this->postJson('/api/v1/public/documents/sign/'.$signatureToken, [
            'signature_method' => 'typed', 'typed_name' => 'External Signer', 'consent' => true,
        ])->assertOk()->assertJsonPath('data.status', 'signed');

        $document->refresh();
        $this->assertSame('signed', $document->workflow_status);
        $this->assertNotNull($document->locked_at);
        $this->assertDatabaseHas('document_review_events', ['generated_document_id' => $document->id, 'event' => 'signed']);
    }

    /** Returns a seeded demo user and their active workspace membership. */
    private function userAndMember(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();
        $member = $user->memberships()->with('workspace')->where('status', 'active')->firstOrFail();
        return [$user, $member];
    }

    /** Returns the workspace request header used by authenticated API feature tests. */
    private function headers(int $workspaceId): array
    {
        return ['X-Workspace-Id' => (string) $workspaceId];
    }
}
