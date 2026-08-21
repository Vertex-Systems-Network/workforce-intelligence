<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Protects Document Studio V6 multi-page, autosave, preflight and batch-generation architecture contracts. */
class DocumentStudioV6ContractTest extends TestCase
{
    /** Verifies mutable V6 drafts are additive and remain separate from immutable template versions. */
    public function test_v6_draft_schema_and_routes_are_present(): void
    {
        $migration = (string) file_get_contents(base_path('database/migrations/2026_08_20_000500_create_document_template_drafts.php'));
        $model = (string) file_get_contents(base_path('app/Models/DocumentTemplateDraft.php'));
        $service = (string) file_get_contents(base_path('app/Services/Documents/DocumentStudioV6Service.php'));
        $routes = (string) file_get_contents(base_path('routes/documents.php'));

        $this->assertStringContainsString('document_template_drafts', $migration);
        $this->assertStringContainsString('revision', $migration);
        $this->assertStringContainsString('document_template_id', $model);
        foreach (['saveDraft', 'draftPayload', 'discardDraft', 'preflight', 'batchGenerate'] as $method) {
            $this->assertStringContainsString("function {$method}", $service);
        }
        foreach (['/draft', '/preflight', '/batch-generate'] as $uri) {
            $this->assertStringContainsString($uri, $routes);
        }
        $this->assertStringContainsString("DocumentTemplateDraft::query()->where('document_template_id'", (string) file_get_contents(base_path('app/Services/Documents/DocumentTemplateService.php')));
    }

    /** Verifies V6 page containers render as authored pages while the legacy V4 schema remains readable. */
    public function test_v6_renderer_and_catalog_keep_page_and_legacy_compatibility(): void
    {
        $renderer = (string) file_get_contents(base_path('app/Services/Documents/DocumentTemplateRenderer.php'));
        $service = (string) file_get_contents(base_path('app/Services/Documents/DocumentTemplateService.php'));
        $catalog = (string) file_get_contents(base_path('app/Services/Documents/DocumentTemplateCatalog.php'));

        $this->assertStringContainsString("'page' => 'Page'", $catalog);
        $this->assertStringContainsString('data-page-id=', $renderer);
        $this->assertStringContainsString('legacy-page-1', $renderer);
        $this->assertStringContainsString('V6 page containers cannot be mixed with legacy root blocks.', $service);
        $this->assertStringContainsString('A V6 document can contain at most 50 authored pages.', $service);
        $this->assertStringContainsString("['studio_version']=6", str_replace(' ', '', $service));
    }

    /** Verifies the V6 client shell exposes multi-page authoring, Media DAM, data, review and preflight controls. */
    public function test_v6_frontend_exposes_multi_page_studio_contracts(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/pages/Documents.tsx'));
        foreach (['Pages', 'Layers', 'Blocks', 'Assets', 'Multi-page live canvas', 'Media Library', 'Run server preflight', 'Batch generate', 'Review'] as $marker) {
            $this->assertStringContainsString($marker, $source);
        }
        foreach (['/draft', '/preflight', '/batch-generate', 'normalizeV6Schema', 'autosaveDraft'] as $marker) {
            $this->assertStringContainsString($marker, $source);
        }
        $this->assertStringNotContainsString('window.prompt(', $source);
        $this->assertStringNotContainsString('window.confirm(', $source);
    }

    /** Verifies advanced V6 brand, page-master, linked-component and persistent batch contracts. */
    public function test_v6_advanced_authoring_and_background_batch_contracts_are_present(): void
    {
        $migration=(string)file_get_contents(base_path('database/migrations/2026_08_20_001000_create_document_studio_v6_advanced_authoring.php'));
        $service=(string)file_get_contents(base_path('app/Services/Documents/DocumentStudioV6Service.php'));
        $renderer=(string)file_get_contents(base_path('app/Services/Documents/DocumentTemplateRenderer.php'));
        $page=(string)file_get_contents(base_path('resources/js/pages/Documents.tsx'));
        $support=(string)file_get_contents(base_path('resources/js/documents/studio/DocumentStudioSupport.tsx'));
        $routes=(string)file_get_contents(base_path('routes/documents.php'));
        $console=(string)file_get_contents(base_path('routes/console.php'));

        foreach(['document_brand_kits','document_page_masters','document_batch_jobs'] as $table)$this->assertStringContainsString($table,$migration);
        foreach(['createBrandKit','createPageMaster','queueBatchGenerate','processQueuedBatches'] as $method)$this->assertStringContainsString("function {$method}",$service);
        foreach(['DocumentBrandKit','DocumentPageMaster','formatTableValue'] as $marker)$this->assertStringContainsString($marker,$renderer);
        foreach(['Save current brand kit','Save current page master','Update source','documents.batch-jobs.v6'] as $marker)$this->assertStringContainsString($marker,$page);
        $this->assertStringContainsString('Detach to local copy',$support);
        $this->assertStringContainsString('/documents/templates/{template}/batch-jobs',$routes);
        $this->assertStringContainsString('workintel:process-document-batches',$console);
    }

    /** Verifies final-closure idempotency, page overrides, Media DAM brand logos and policy enforcement contracts. */
    public function test_v6_final_closure_hardening_contracts_are_present(): void
    {
        $migration=(string)file_get_contents(base_path('database/migrations/2026_08_20_001100_harden_document_studio_v6_batch_queue.php'));
        $service=(string)file_get_contents(base_path('app/Services/Documents/DocumentStudioV6Service.php'));
        $template=(string)file_get_contents(base_path('app/Services/Documents/DocumentTemplateService.php'));
        $workflow=(string)file_get_contents(base_path('app/Services/Documents/DocumentStudioV4Service.php'));
        $renderer=(string)file_get_contents(base_path('app/Services/Documents/DocumentTemplateRenderer.php'));
        $page=(string)file_get_contents(base_path('resources/js/pages/Documents.tsx'));

        foreach(['client_request_id','heartbeat_at','attempt_count','last_error'] as $marker)$this->assertStringContainsString($marker,$migration);
        foreach(['recoverStaleBatches','settingsReferenceExists','pageMasterReferenceExists','brand_kit.logo_missing'] as $marker)$this->assertStringContainsString($marker,$service);
        $this->assertStringContainsString('$schema,$settings',$template);
        $this->assertStringContainsString("'workflow_policy'=>",$template);
        foreach(['workflowPolicy','must complete review before approval','All required signatures must be completed before final lock'] as $marker)$this->assertStringContainsString($marker,$workflow);
        foreach(['brandLogoAssetId','pageSettings','pageStyle'] as $marker)$this->assertStringContainsString($marker,$renderer);
        foreach(['Choose Brand Kit logo','Page master override','Policy snapshot','randomUUID'] as $marker)$this->assertStringContainsString($marker,$page);
    }
}
