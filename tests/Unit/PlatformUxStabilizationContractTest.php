<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the shared UI, information architecture and media-selection regressions reported during Block P stabilization. */
class PlatformUxStabilizationContractTest extends TestCase
{
    /** Ensure unauthenticated restore, portals and stale tooltips follow the stabilized UI contract. */
    public function test_auth_restore_and_overlay_contracts_are_stable(): void
    {
        $root = dirname(__DIR__, 2);
        $auth = file_get_contents($root.'/resources/js/auth/authService.ts');
        $client = file_get_contents($root.'/resources/js/api/client.ts');
        $ui = file_get_contents($root.'/resources/js/design-system/index.tsx');
        $css = file_get_contents($root.'/resources/js/design-system/toolkit.css');
        $this->assertStringContainsString("silent: true", $auth);
        $this->assertStringContainsString('!silent && !authInvalidated', $client);
        $this->assertStringContainsString('portalId="workintel-datepicker-portal"', $ui);
        $this->assertStringContainsString('workintel:overlay-open', $ui);
        $this->assertStringContainsString('#workintel-datepicker-portal{z-index:1860}', $css);
    }

    /** Ensure menu taxonomy and task presentation controls no longer collapse unrelated pages into ambiguous labels. */
    public function test_navigation_and_collection_view_contracts_are_classified(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = file_get_contents($root.'/resources/js/navigation.manifest.json');
        $access = file_get_contents($root.'/resources/js/access.ts');
        $tasks = file_get_contents($root.'/resources/js/pages/Tasks.tsx');
        $this->assertStringContainsString('"time-attendance"', $manifest);
        $this->assertStringContainsString('"content-studio"', $manifest);
        $this->assertStringContainsString('MULTI_PAGE_MODULE_LABELS', $access);
        $this->assertStringContainsString('ViewModeToggle', $tasks);
        $this->assertStringContainsString('gridLabel="Board" tableLabel="List"', $tasks);
    }

    /** Ensure image and file workflows expose one reusable media chooser instead of competing upload buttons. */
    public function test_media_and_studio_contracts_are_unified(): void
    {
        $root = dirname(__DIR__, 2);
        $field = file_get_contents($root.'/resources/js/media/MediaFileField.tsx');
        $picker = file_get_contents($root.'/resources/js/media/MediaPicker.tsx');
        $website = file_get_contents($root.'/resources/js/pages/WebsiteStudio.tsx');
        $documents = file_get_contents($root.'/resources/js/pages/Documents.tsx');
        $chat = file_get_contents($root.'/resources/js/pages/Chat.tsx');
        $doctor = file_get_contents($root.'/app/Console/Commands/DataLifecycleMediaDoctor.php');
        $this->assertStringContainsString("Choose {imagesOnly?'image':'file'}", $field);
        $this->assertStringContainsString('Media Library', $picker);
        $this->assertStringContainsString('mediaUploadConstraintError', $picker);
        $this->assertStringContainsString('selectedAssets', $picker);
        $this->assertStringContainsString('websitePageAudit', $website);
        $this->assertStringContainsString('documentPreflight', $documents);
        $this->assertStringContainsString('ConversationFilter', $chat);
        $this->assertStringContainsString('Private Media Library storage is not writable.', $doctor);
        $this->assertStringContainsString('Media upload limits:', $doctor);
    }
}
