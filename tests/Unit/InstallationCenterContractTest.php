<?php

namespace Tests\Unit;

use App\Support\InstallationGuideCatalog;
use PHPUnit\Framework\TestCase;

/** Provides p9 installation contract test behavior within the WorkIntel application. */
class InstallationCenterContractTest extends TestCase
{
    /** Proves platform guides preserve verification/startup commands without asking users for a server URL. */
    public function test_platform_guides_match_server_bound_installation_contract(): void
    {
        foreach (['windows-agent', 'macos-agent', 'linux-agent', 'chrome-edge-extension', 'firefox-extension', 'admin-production', 'repair-uninstall'] as $key) {
            $this->assertNotNull(InstallationGuideCatalog::get($key));
        }

        $windows = json_encode(InstallationGuideCatalog::get('windows-agent'), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Canonical release verified before binding', $windows);
        $this->assertStringContainsString('SHA-256', $windows);
        $this->assertStringContainsString('schtasks', $windows);
        $this->assertStringNotContainsString('{{server_url}}', $windows);

        $macos = json_encode(InstallationGuideCatalog::get('macos-agent'), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('launchctl', $macos);
        $this->assertStringNotContainsString('{{server_url}}', $macos);

        $linux = json_encode(InstallationGuideCatalog::get('linux-agent'), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('systemctl --user', $linux);
        $this->assertStringNotContainsString('{{server_url}}', $linux);
    }

    /** Handles the test downloads ui has real guide actions operation for the current WorkIntel workflow. */
    public function test_downloads_ui_has_real_guide_actions(): void
    {
        $source = file_get_contents(base_path('resources/js/pages/Downloads.tsx'));
        foreach (['/api/v1/installation-center', 'downloads.create_enrollment', '/pdf', 'downloads.test_installation', 'completed_steps'] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }
}
