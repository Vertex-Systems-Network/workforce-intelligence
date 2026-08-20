<?php

namespace Tests\Unit;

use App\Support\PlanCatalog;
use App\Support\WebsiteBuilderCatalog;
use PHPUnit\Framework\TestCase;

/** Protects Website & Portal Builder Block H architecture without requiring a database. */
class WebsitePortalBuilderContractTest extends TestCase
{
    /** Verifies the page/section catalog and plan-controlled website capabilities remain registered. */
    public function test_catalog_and_plan_capabilities_are_present(): void
    {
        $this->assertContains('home', WebsiteBuilderCatalog::PAGE_TYPES);
        $this->assertContains('portfolio', WebsiteBuilderCatalog::PAGE_TYPES);
        $this->assertContains('form', WebsiteBuilderCatalog::SECTION_TYPES);
        $this->assertContains('gallery', WebsiteBuilderCatalog::SECTION_TYPES);
        $this->assertTrue(PlanCatalog::DEFINITIONS['gold']['entitlements']['feature.website_builder']);
        $this->assertSame(25, PlanCatalog::DEFINITIONS['gold']['entitlements']['limit.website_pages']);
        $this->assertFalse(PlanCatalog::DEFINITIONS['free']['entitlements']['feature.website_builder']);
    }

    /** Verifies versioned persistence, encrypted leads and verified custom-domain support are additive. */
    public function test_persistence_and_security_contract_is_present(): void
    {
        $migration = file_get_contents(base_path('database/migrations/2026_08_14_000400_create_website_portal_builder.php'));
        $submission = file_get_contents(base_path('app/Models/WebsiteFormSubmission.php'));
        $service = file_get_contents(base_path('app/Services/WebsiteBuilderService.php'));
        foreach (['website_sites', 'website_pages', 'website_page_versions', 'website_reusable_sections', 'website_forms', 'website_form_submissions'] as $table) {
            $this->assertStringContainsString($table, $migration);
        }
        $this->assertStringContainsString("'payload' => 'encrypted:array'", $submission);
        $this->assertStringContainsString('lockForUpdate()->firstOrFail()', $service);
        $this->assertStringContainsString('normalizeSchema', $service);
        $this->assertStringContainsString('syncPublishedMedia', $service);
        $this->assertStringContainsString("'website.page_published'", $service);
        $this->assertStringContainsString("'website.lead_received'", $service);
    }

    /** Verifies the editor/public renderer share one schema while preview interactions remain safe. */
    public function test_visual_builder_and_public_delivery_contract_is_present(): void
    {
        $studio = file_get_contents(base_path('resources/js/pages/WebsiteStudio.tsx'));
        $renderer = file_get_contents(base_path('resources/js/website/WebsiteRenderer.tsx'));
        $app = file_get_contents(base_path('resources/js/app.tsx'));
        $web = file_get_contents(base_path('routes/web.php'));
        foreach (['DndContext', 'WebsiteRenderer', 'Save as reusable section', 'Archive page', 'DataGrid', 'MediaPicker'] as $needle) {
            $this->assertStringContainsString($needle, $studio);
        }
        $this->assertStringContainsString('if(preview)return', $renderer);
        $this->assertStringContainsString('preview={preview}', $renderer);
        $this->assertStringContainsString('__WORKINTEL_PUBLIC_WEBSITE_HOST__', $app);
        $this->assertStringContainsString("where('purpose', 'website')", $web);
        $this->assertStringContainsString('publicWebsiteMeta', $web);
    }
}
