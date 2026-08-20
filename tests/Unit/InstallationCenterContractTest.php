<?php
namespace Tests\Unit;
use App\Support\InstallationGuideCatalog;
use PHPUnit\Framework\TestCase;
/** Provides p9 installation contract test behavior within the WorkIntel application. */ class InstallationCenterContractTest extends TestCase
{
    /** Handles the test platform guides and verification commands exist operation for the current WorkIntel workflow. */ public function test_platform_guides_and_verification_commands_exist():void
    {
        foreach(['windows-agent','macos-agent','linux-agent','chrome-edge-extension','firefox-extension','admin-production','repair-uninstall'] as $key)$this->assertNotNull(InstallationGuideCatalog::get($key));
        $windows=InstallationGuideCatalog::get('windows-agent');$this->assertStringContainsString('Get-FileHash',json_encode($windows));$this->assertStringContainsString('schtasks',json_encode($windows));
        $this->assertStringContainsString('launchctl',json_encode(InstallationGuideCatalog::get('macos-agent')));$this->assertStringContainsString('systemctl --user',json_encode(InstallationGuideCatalog::get('linux-agent')));
    }
    /** Handles the test downloads ui has real guide actions operation for the current WorkIntel workflow. */ public function test_downloads_ui_has_real_guide_actions():void
    {
        $source=file_get_contents(base_path('resources/js/pages/Downloads.tsx'));foreach(['/api/v1/installation-center','downloads.create_enrollment','/pdf','downloads.test_installation','completed_steps'] as $needle)$this->assertStringContainsString($needle,$source);
    }
}
