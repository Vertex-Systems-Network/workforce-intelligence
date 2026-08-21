<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the M8 Website Studio V3 autosave, staging, review and public-preview contracts. */
class WebsiteStudioV3ContractTest extends TestCase
{
    /** Ensures mutable autosave and immutable versions remain separate persistence concerns. */
    public function test_autosave_and_immutable_version_contracts_are_separate(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string) file_get_contents($root.'/app/Services/WebsiteBuilderService.php');
        $migration = (string) file_get_contents($root.'/database/migrations/2026_08_20_000300_create_website_page_drafts.php');
        $this->assertStringContainsString('WebsitePageDraft', $service);
        $this->assertStringContainsString('WebsitePageVersion::create', $service);
        $this->assertStringContainsString('website_page_drafts', $migration);
        $this->assertStringContainsString('revision', $migration);
    }

    /** Ensures staging, share-preview, review and linked-component contracts are wired. */
    public function test_staging_review_and_linked_component_contracts_are_present(): void
    {
        $root = dirname(__DIR__, 2);
        $studio = (string) file_get_contents($root.'/resources/js/pages/WebsiteStudio.tsx');
        $support = (string) file_get_contents($root.'/resources/js/website/studio/WebsiteStudioSupport.tsx');
        $frontend = $studio."\n".$support;
        $service = (string) file_get_contents($root.'/app/Services/WebsiteBuilderService.php');
        $migration = (string) file_get_contents($root.'/database/migrations/2026_08_20_000400_create_website_staging_review_and_component_links.php');
        foreach (['Stage','Publish staging','ReviewInspector','linked_reusable_uuid','Responsive overrides'] as $needle) $this->assertStringContainsString($needle, $frontend);
        foreach (['stagePage','createPreviewToken','previewPayload','pageComments','syncReusableLinks','propagateReusableSection','bindDynamicValues'] as $needle) $this->assertStringContainsString($needle, $service);
        foreach (['website_preview_tokens','website_page_comments','website_reusable_section_links','staged_version'] as $needle) $this->assertStringContainsString($needle, $migration);
    }

    /** Ensures server preflight remains the publish safety boundary. */
    public function test_builder_shell_and_publish_preflight_contracts_are_present(): void
    {
        $root = dirname(__DIR__, 2);
        $studio = (string) file_get_contents($root.'/resources/js/pages/WebsiteStudio.tsx');
        $support = (string) file_get_contents($root.'/resources/js/website/studio/WebsiteStudioSupport.tsx');
        $frontend = $studio."\n".$support;
        $service = (string) file_get_contents($root.'/app/Services/WebsiteBuilderService.php');
        foreach (['website-builder-shell--v3','website-rail-tabs','website-inspector-tabs','Run preflight'] as $needle) $this->assertStringContainsString($needle, $frontend);
        foreach (['preflightPage','media.rights_expired','form.missing','url.unsafe','binding.unknown'] as $needle) $this->assertStringContainsString($needle, $service);
    }
}
