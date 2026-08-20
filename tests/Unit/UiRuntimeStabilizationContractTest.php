<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Protects the source contracts that prevent install-time and UI-system regressions. */
class UiRuntimeStabilizationContractTest extends TestCase
{
    /** Ensure Composer prepares runtime directories and avoids the DOM-dependent console package discover path. */
    public function test_composer_uses_runtime_preparation_and_direct_package_manifest_builder(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
        $scripts = $composer['scripts']['post-autoload-dump'] ?? [];
        $this->assertContains('@php tools/prepare-runtime.php', $scripts);
        $this->assertContains('@php tools/discover-packages.php', $scripts);
        $this->assertNotContains('@php artisan package:discover --ansi', $scripts);
        $this->assertStringContainsString('tests/bootstrap.php', file_get_contents(base_path('phpunit.xml')));
    }

    /** Ensure the frontend contains the shared styling, toast and per-page customization foundations. */
    public function test_frontend_ui_system_contracts_exist(): void
    {
        $toolkit = file_get_contents(resource_path('js/design-system/toolkit.css'));
        $ui = file_get_contents(resource_path('js/design-system/index.tsx'));
        $dashboard = file_get_contents(resource_path('js/components/DashboardGrid.tsx'));
        $this->assertStringContainsString('.ui-file-input', $toolkit);
        $this->assertStringContainsString('.ui-toast-viewport', $toolkit);
        $this->assertStringContainsString('FileInput', $ui);
        $this->assertStringContainsString('ui-select-menu', $ui);
        $this->assertStringContainsString('Manage widgets', $dashboard);
        $this->assertStringContainsString('visible_widgets', $dashboard);
    }

    /** Ensure overlay, grid and test-environment hardening remain wired through shared primitives. */
    public function test_ui_foundation_v3_and_test_preflight_contracts_exist(): void
    {
        $ui = file_get_contents(resource_path('js/design-system/index.tsx'));
        $css = file_get_contents(resource_path('js/design-system/toolkit.css'));
        $preflight = file_get_contents(base_path('tools/runtime-preflight.php'));
        $testCase = file_get_contents(base_path('tests/TestCase.php'));
        foreach (['usePortalPosition', 'ui-dropdown--portal', 'FormGrid', 'RefreshButton', 'DataGrid'] as $token) {
            $this->assertStringContainsString($token, $ui.$css);
        }
        $this->assertStringContainsString('pdo_sqlite', $preflight);
        $this->assertStringContainsString('markTestSkipped', $testCase);
    }
}
