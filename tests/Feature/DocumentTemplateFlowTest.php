<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides p6 document template flow test behavior within the WorkIntel application. */ class DocumentTemplateFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the set up operation for the current WorkIntel workflow. */ protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    /** Handles the test owner can version preview generate and download document templates operation for the current WorkIntel workflow. */ public function test_owner_can_version_preview_generate_and_download_document_templates(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);

        $overview = $this->getJson('/api/v1/documents/overview', $headers)->assertOk()->json();
        $this->assertGreaterThanOrEqual(12, count($overview['templates']));
        $this->assertTrue($overview['permissions']['templates_manage']);

        $template = $this->postJson('/api/v1/documents/templates', [
            'name' => 'Custom Invoice P6',
            'document_type' => 'invoice',
            'language' => 'en',
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'primary_color' => '#111827',
            'secondary_color' => '#6B7280',
            'content_schema' => [
                ['id'=>'title','type'=>'heading','text'=>'Invoice {{invoice.number}}','level'=>1],
                ['id'=>'body','type'=>'text','text'=>'Bill to {{client.company_name}}'],
                ['id'=>'total','type'=>'text','text'=>'Total {{invoice.currency}} {{invoice.total}}'],
            ],
        ], $headers)->assertCreated()->json('data');
        $this->assertSame(1, $template['current_version']);

        $this->putJson('/api/v1/documents/templates/'.$template['id'], [
            'name' => 'Custom Invoice P6 v2',
            'content_schema' => [
                ['id'=>'title','type'=>'heading','text'=>'INVOICE {{invoice.number}}','level'=>1],
                ['id'=>'body','type'=>'text','text'=>'Client {{client.company_name}}'],
            ],
            'change_note' => 'Brand wording update',
        ], $headers)->assertOk()->assertJsonPath('data.current_version', 2);

        $this->postJson('/api/v1/documents/templates/'.$template['id'].'/default', [], $headers)
            ->assertOk()->assertJsonPath('data.is_default', true);
        $this->postJson('/api/v1/documents/templates/'.$template['id'].'/preview', [], $headers)
            ->assertOk()->assertJsonPath('context.invoice.number', 'INV-2026-0001');

        $generated = $this->postJson('/api/v1/documents/templates/'.$template['id'].'/generate', [], $headers)
            ->assertCreated()->assertJsonPath('data.document_type', 'invoice')->json('data');
        $row = GeneratedDocument::findOrFail($generated['id']);
        Storage::disk('local')->assertExists($row->path);
        $this->assertStringStartsWith('%PDF-1.4', Storage::disk('local')->get($row->path));

        $this->get('/api/v1/documents/generated/'.$row->id.'/download', $headers)
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    /** Handles the test documents module can be disabled without deleting templates or generated files operation for the current WorkIntel workflow. */ public function test_documents_module_can_be_disabled_without_deleting_templates_or_generated_files(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner); $headers = $this->headers($member->workspace_id);
        $template = DocumentTemplate::where('workspace_id', $member->workspace_id)->where('document_type','invoice')->firstOrFail();
        $generated = $this->postJson('/api/v1/documents/templates/'.$template->id.'/generate', [], $headers)->assertCreated()->json('data');

        $this->patchJson('/api/v1/modules/documents', ['is_enabled'=>false], $headers)->assertOk();
        $this->getJson('/api/v1/documents/overview', $headers)->assertStatus(423);
        $this->assertDatabaseHas('document_templates', ['id'=>$template->id]);
        $this->assertDatabaseHas('generated_documents', ['id'=>$generated['id']]);
    }

    /** Handles the test generated sensitive documents require underlying domain permission operation for the current WorkIntel workflow. */ public function test_generated_sensitive_documents_require_underlying_domain_permission(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        [$managerUser, $managerMember] = $this->userAndMember('manager@acme.test');
        Sanctum::actingAs($owner); $headers = $this->headers($ownerMember->workspace_id);
        $template = DocumentTemplate::where('workspace_id',$ownerMember->workspace_id)->where('document_type','payslip')->firstOrFail();
        $generated = $this->postJson('/api/v1/documents/templates/'.$template->id.'/generate', [], $headers)->assertCreated()->json('data');

        Sanctum::actingAs($managerUser);
        $overview = $this->getJson('/api/v1/documents/overview', $headers)->assertOk()->json();
        $this->assertFalse(collect($overview['generated'])->contains(fn ($row) => (int) $row['id'] === (int) $generated['id']));
        $this->get('/api/v1/documents/generated/'.$generated['id'].'/download', $headers)->assertForbidden();
    }

    /** Handles the test default template selection does not leak another legal entity template operation for the current WorkIntel workflow. */ public function test_default_template_selection_does_not_leak_another_legal_entity_template(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner); $headers = $this->headers($member->workspace_id);
        $global = DocumentTemplate::where('workspace_id',$member->workspace_id)->where('document_type','invoice')->whereNull('legal_entity_id')->firstOrFail();
        $legalEntity = \App\Models\LegalEntity::where('workspace_id',$member->workspace_id)->first();
        if (! $legalEntity) $this->markTestSkipped('Demo workspace has no legal entity.');

        $entityTemplate = $this->postJson('/api/v1/documents/templates', [
            'name'=>'Entity Invoice','document_type'=>'invoice','language'=>'en','legal_entity_id'=>$legalEntity->id,
        ], $headers)->assertCreated()->json('data');
        $this->postJson('/api/v1/documents/templates/'.$entityTemplate['id'].'/default', [], $headers)->assertOk();

        $selected = app(\App\Services\Documents\DocumentTemplateService::class)->defaultTemplate($member->workspace, 'invoice');
        $this->assertSame($global->id, $selected?->id);
    }

    /** Handles the user and member operation for the current WorkIntel workflow. */ private function userAndMember(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();
        $member = $user->memberships()->with('workspace')->where('status', 'active')->firstOrFail();
        return [$user, $member];
    }

    /** Handles the headers operation for the current WorkIntel workflow. */ private function headers(int $workspaceId): array { return ['X-Workspace-Id'=>(string)$workspaceId]; }
}
