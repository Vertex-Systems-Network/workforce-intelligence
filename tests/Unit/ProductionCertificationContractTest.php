<?php

namespace Tests\Unit;

use App\Support\ProductionCertificationCatalog;
use PHPUnit\Framework\TestCase;

/** Protects Block I production certification and browser-QA contracts without a database. */
class ProductionCertificationContractTest extends TestCase
{
    /** Verify the latest platform tables and critical delivery routes remain in certification inventory. */
    public function test_release_catalog_contains_latest_platform_landmarks(): void
    {
        foreach (['workspace_client_payment_gateways', 'document_signature_requests', 'website_page_versions', 'media_assets'] as $table) {
            $this->assertContains($table, ProductionCertificationCatalog::REQUIRED_TABLES);
        }
        foreach (['health/live', 'api/v1/chat/conversations', 'api/v1/seller', 'api/v1/website/overview'] as $route) {
            $this->assertContains($route, ProductionCertificationCatalog::REQUIRED_ROUTE_URIS);
        }
    }

    /** Verify browser certification scripts, viewports and release gates are wired. */
    public function test_browser_certification_and_release_gates_are_registered(): void
    {
        $package = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('@playwright/test', $package['devDependencies']);
        $this->assertArrayHasKey('test:e2e:public', $package['scripts']);
        $this->assertArrayHasKey('test:e2e:full', $package['scripts']);

        $config = (string) file_get_contents(base_path('tools/playwright.config.mjs'));
        foreach (['desktop', 'tablet', 'mobile', '1440', '1024', '390'] as $needle) $this->assertStringContainsString($needle, $config);

        $release = (string) file_get_contents(base_path('verify-release.cmd'));
        $clean = (string) file_get_contents(base_path('verify-clean-install.cmd'));
        $this->assertStringContainsString('Browser public certification', $release);
        $this->assertStringContainsString('Browser full certification', $clean);
    }
}
