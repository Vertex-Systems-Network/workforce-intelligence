<?php

namespace Tests\Feature;

use App\Models\DocumentBatchJob;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Services\Documents\DocumentStudioV6Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Exercises Document Studio V6 autosave, multi-page rendering and immutable version behavior end to end. */
class DocumentStudioV6FlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seeds the demo workspace and isolates generated document storage for V6 feature coverage. */
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    /** Verifies mutable autosave never increments immutable versions and explicit save clears the draft. */
    public function test_v6_autosave_preflight_preview_and_version_save_are_separated(): void
    {
        $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
        $member = $owner->memberships()->where('status', 'active')->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $member->workspace_id];
        $template = DocumentTemplate::query()->where('workspace_id', $member->workspace_id)->where('document_type', 'invoice')->firstOrFail();
        $beforeVersion = (int) $template->current_version;

        $schema = [
            ['id' => 'page-one', 'type' => 'page', 'label' => 'Invoice summary', 'children' => [
                ['id' => 'page-one-heading', 'type' => 'heading', 'text' => 'Invoice {{invoice.number}}', 'level' => 1],
                ['id' => 'page-one-text', 'type' => 'rich_text', 'html' => '<p>Prepared for {{client.company_name}}</p>'],
            ]],
            ['id' => 'page-two', 'type' => 'page', 'label' => 'Totals', 'children' => [
                ['id' => 'page-two-formula', 'type' => 'formula', 'label' => 'Invoice total', 'expression' => 'invoice.subtotal + invoice.tax_total', 'decimals' => 2],
            ]],
        ];
        $settings = ['studio_version' => 6, 'header' => ['enabled' => true, 'text' => 'WorkIntel Invoice'], 'footer' => ['enabled' => true, 'text' => 'Page footer']];

        $this->putJson('/api/v1/documents/templates/'.$template->id.'/draft', [
            'content_schema' => $schema,
            'settings' => $settings,
            'metadata' => ['name' => $template->name],
        ], $headers)->assertOk()->assertJsonPath('data.revision', 1);

        $template->refresh();
        $this->assertSame($beforeVersion, (int) $template->current_version);
        $this->assertDatabaseHas('document_template_drafts', ['document_template_id' => $template->id, 'revision' => 1]);

        $this->getJson('/api/v1/documents/templates/'.$template->id, $headers)
            ->assertOk()->assertJsonPath('draft.revision', 1)->assertJsonPath('draft.content_schema.0.id', 'page-one');

        $this->postJson('/api/v1/documents/templates/'.$template->id.'/preflight', [
            'content_schema' => $schema,
            'settings' => $settings,
        ], $headers)->assertOk()->assertJsonPath('data.errors', 0)->assertJsonPath('data.stats.page_count', 2);

        $preview = $this->postJson('/api/v1/documents/templates/'.$template->id.'/live-preview', [
            'content_schema' => $schema,
            'settings' => $settings,
        ], $headers)->assertOk()->json();
        $this->assertStringContainsString('data-page-id="page-one"', (string) ($preview['html'] ?? ''));
        $this->assertStringContainsString('data-page-id="page-two"', (string) ($preview['html'] ?? ''));

        $this->putJson('/api/v1/documents/templates/'.$template->id, [
            'content_schema' => $schema,
            'settings' => $settings,
            'change_note' => 'Document Studio V6 multi-page save',
        ], $headers)->assertOk()->assertJsonPath('data.current_version', $beforeVersion + 1);

        $this->assertDatabaseMissing('document_template_drafts', ['document_template_id' => $template->id]);
        $this->assertDatabaseHas('document_template_versions', ['document_template_id' => $template->id, 'version' => $beforeVersion + 1]);
    }

    /** Verifies the V6 batch API rejects unbounded source lists before generation begins. */
    public function test_v6_batch_generation_is_bounded_to_fifty_sources(): void
    {
        $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
        $member = $owner->memberships()->where('status', 'active')->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $member->workspace_id];
        $template = DocumentTemplate::query()->where('workspace_id', $member->workspace_id)->where('document_type', 'invoice')->firstOrFail();

        $this->postJson('/api/v1/documents/templates/'.$template->id.'/batch-generate', [
            'source_ids' => range(1, 51),
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('source_ids');
    }


    /** Verifies advanced V6 resources, linked-component versioning and persistent large-batch queue boundaries. */
    public function test_v6_brand_page_master_linked_components_and_large_batch_queue_are_workspace_scoped(): void
    {
        $owner=User::query()->where('email','owner@acme.test')->firstOrFail();
        $member=$owner->memberships()->where('status','active')->firstOrFail();
        Sanctum::actingAs($owner);$headers=['X-Workspace-Id'=>(string)$member->workspace_id];
        $template=DocumentTemplate::query()->where('workspace_id',$member->workspace_id)->where('document_type','invoice')->firstOrFail();

        $brand=$this->postJson('/api/v1/documents/brand-kits',['name'=>'Corporate','primary_color'=>'#112233','secondary_color'=>'#445566','accent_color'=>'#778899','font_family'=>'Arial'],$headers)->assertCreated()->assertJsonPath('data.name','Corporate')->json('data');
        $master=$this->postJson('/api/v1/documents/page-masters',['name'=>'A4 Corporate','page_settings'=>['margin_top'=>16,'margin_right'=>18,'margin_bottom'=>20,'margin_left'=>18],'header_settings'=>['enabled'=>true,'text'=>'Corporate Header'],'footer_settings'=>['enabled'=>true,'text'=>'Corporate Footer']],$headers)->assertCreated()->assertJsonPath('data.name','A4 Corporate')->json('data');

        $this->getJson('/api/v1/documents/v6/resources',$headers)->assertOk()->assertJsonPath('data.brand_kits.0.id',$brand['id'])->assertJsonPath('data.page_masters.0.id',$master['id']);

        $component=$this->postJson('/api/v1/documents/components',['name'=>'Linked Legal Note','category'=>'Legal','content_schema'=>[['id'=>'legal-note','type'=>'text','text'=>'Version one']]],$headers)->assertCreated()->assertJsonPath('data.version',1)->json('data');
        $this->putJson('/api/v1/documents/components/'.$component['id'],['content_schema'=>[['id'=>'legal-note','type'=>'text','text'=>'Version two']]],$headers)->assertOk()->assertJsonPath('data.version',2);

        $this->postJson('/api/v1/documents/templates/'.$template->id.'/batch-jobs',['source_ids'=>range(1,51)],$headers)->assertStatus(202)->assertJsonPath('data.requested_count',51)->assertJsonPath('data.status','queued');
        $this->assertDatabaseHas('document_batch_jobs',['workspace_id'=>$member->workspace_id,'document_template_id'=>$template->id,'requested_count'=>51,'status'=>'queued']);
        $this->postJson('/api/v1/documents/templates/'.$template->id.'/batch-jobs',['source_ids'=>range(1,501)],$headers)->assertUnprocessable()->assertJsonValidationErrors('source_ids');
    }


    /** Verifies persistent batch retries are idempotent and stale running jobs recover without rewinding progress. */
    public function test_v6_large_batch_idempotency_and_stale_recovery_are_durable(): void
    {
        $owner=User::query()->where('email','owner@acme.test')->firstOrFail();
        $member=$owner->memberships()->where('status','active')->firstOrFail();
        Sanctum::actingAs($owner);$headers=['X-Workspace-Id'=>(string)$member->workspace_id];
        $template=DocumentTemplate::query()->where('workspace_id',$member->workspace_id)->where('document_type','invoice')->firstOrFail();
        $payload=['source_ids'=>range(1,51),'client_request_id'=>'m9-final-retry-001'];
        $first=$this->postJson('/api/v1/documents/templates/'.$template->id.'/batch-jobs',$payload,$headers)->assertStatus(202)->json('data');
        $second=$this->postJson('/api/v1/documents/templates/'.$template->id.'/batch-jobs',$payload,$headers)->assertStatus(202)->json('data');
        $this->assertSame((int)$first['id'],(int)$second['id']);
        $this->assertSame(1,DocumentBatchJob::query()->where('workspace_id',$member->workspace_id)->where('client_request_id','m9-final-retry-001')->count());
        $this->postJson('/api/v1/documents/templates/'.$template->id.'/batch-jobs',['source_ids'=>range(2,52),'client_request_id'=>'m9-final-retry-001'],$headers)->assertUnprocessable()->assertJsonValidationErrors('client_request_id');
        $job=DocumentBatchJob::findOrFail($first['id']);$job->update(['status'=>'running','processed_count'=>7,'started_at'=>now()->subMinutes(30),'heartbeat_at'=>now()->subMinutes(30)]);
        $recovered=app(DocumentStudioV6Service::class)->recoverStaleBatches(10);
        $this->assertSame(1,$recovered);$job->refresh();$this->assertSame('queued',$job->status);$this->assertSame(7,(int)$job->processed_count);
    }

    /** Verifies per-page masters render and generated workflow policy is enforced in order. */
    public function test_v6_page_master_override_and_generated_workflow_policy_are_enforced(): void
    {
        $owner=User::query()->where('email','owner@acme.test')->firstOrFail();
        $member=$owner->memberships()->where('status','active')->firstOrFail();
        Sanctum::actingAs($owner);$headers=['X-Workspace-Id'=>(string)$member->workspace_id];
        $template=DocumentTemplate::query()->where('workspace_id',$member->workspace_id)->where('document_type','invoice')->firstOrFail();
        $master=$this->postJson('/api/v1/documents/page-masters',['name'=>'Final Page Master','page_settings'=>['margin_top'=>12,'margin_right'=>13,'margin_bottom'=>14,'margin_left'=>15,'background'=>'#FFFFFF'],'header_settings'=>['enabled'=>true,'text'=>'FINAL MASTER HEADER'],'footer_settings'=>['enabled'=>true,'text'=>'FINAL MASTER FOOTER']],$headers)->assertCreated()->json('data');
        $schema=[['id'=>'final-page','type'=>'page','label'=>'Final','page_master_id'=>$master['id'],'page_settings'=>['margin_top'=>16,'margin_right'=>17,'margin_bottom'=>18,'margin_left'=>19,'background'=>'#FFFFFF'],'children'=>[['id'=>'final-title','type'=>'heading','text'=>'Final policy document','level'=>1],['id'=>'final-signature','type'=>'signature','label'=>'Authorized Signature','role'=>'Director']]]];
        $settings=['studio_version'=>6,'workflow'=>['review_required'=>true,'approval_required'=>true,'signature_required'=>true,'signer_role'=>'Director']];
        $this->putJson('/api/v1/documents/templates/'.$template->id,['content_schema'=>$schema,'settings'=>$settings,'change_note'=>'M9 final policy'], $headers)->assertOk();
        $preview=$this->postJson('/api/v1/documents/templates/'.$template->id.'/live-preview',['content_schema'=>$schema,'settings'=>$settings],$headers)->assertOk()->json();
        $this->assertStringContainsString('FINAL MASTER HEADER',(string)($preview['html']??''));
        $this->assertStringContainsString('--wi-doc-mt:16.0mm',(string)($preview['html']??''));
        $generated=$this->postJson('/api/v1/documents/templates/'.$template->id.'/generate',[],$headers)->assertCreated()->json('data');
        $document=GeneratedDocument::findOrFail($generated['id']);
        $this->assertTrue((bool)data_get($document->render_metadata,'workflow_policy.review_required'));
        $this->postJson('/api/v1/documents/generated/'.$document->id.'/approve',[],$headers)->assertUnprocessable();
        $this->postJson('/api/v1/documents/generated/'.$document->id.'/review',['note'=>'Reviewed'], $headers)->assertOk();
        $this->postJson('/api/v1/documents/generated/'.$document->id.'/approve',['note'=>'Approved'], $headers)->assertOk();
        $signature=$this->postJson('/api/v1/documents/generated/'.$document->id.'/signature-requests',['signer_name'=>'Final Signer','expires_in_days'=>7],$headers)->assertCreated()->assertJsonPath('data.request.role_label','Director')->json('data');
        $this->postJson('/api/v1/documents/generated/'.$document->id.'/lock',[],$headers)->assertUnprocessable();
        preg_match('#/document-sign/([^/]+)$#',(string)$signature['url'],$match);$token=$match[1]??'';$this->assertNotSame('',$token);
        $this->postJson('/api/v1/public/documents/sign/'.$token,['signature_method'=>'typed','typed_name'=>'Final Signer','consent'=>true])->assertOk();
        $document->refresh();$this->assertSame('signed',$document->workflow_status);$this->assertNotNull($document->locked_at);
    }
}
