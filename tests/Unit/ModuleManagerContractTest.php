<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Provides p4 module contract test behavior within the WorkIntel application. */ class ModuleManagerContractTest extends TestCase
{
    /** Handles the test module manager ui contains required safety controls operation for the current WorkIntel workflow. */ public function test_module_manager_ui_contains_required_safety_controls(): void
    {
        $source = file_get_contents(__DIR__.'/../../resources/js/pages/Modules.tsx');
        foreach (['Apps / Modules', 'Background processing', 'Show in navigation', 'Reset defaults', 'Data-safe switching'] as $label) {
            $this->assertStringContainsString($label, $source);
        }
        $this->assertStringContainsString('cascade_dependents', $source);
        $this->assertStringContainsString('workintel:modules-changed', $source);
    }

    /** Handles the test sidebar and page access consult workspace module state operation for the current WorkIntel workflow. */ public function test_sidebar_and_page_access_consult_workspace_module_state(): void
    {
        $access = file_get_contents(__DIR__.'/../../resources/js/access.ts');
        $sidebar = file_get_contents(__DIR__.'/../../resources/js/components/Sidebar.tsx');
        $this->assertStringContainsString('isModuleEnabled', $access);
        $this->assertStringContainsString('isPageVisibleInNavigation', $sidebar);
        $this->assertSame(1,preg_match('/modules\s*:\s*\[[\'"]modules\.view[\'"]/', $access));
    }

    /** Handles the test kernel surfaces are not workspace switchable operation for the current WorkIntel workflow. */ public function test_kernel_surfaces_are_not_workspace_switchable(): void
    {
        $catalog = file_get_contents(__DIR__.'/../../app/Support/ModuleCatalog.php');
        foreach (['authentication', 'settings', 'roles-access', 'billing', 'downloads'] as $kernel) {
            $this->assertStringNotContainsString("'{$kernel}' => [", $catalog);
        }
    }
}
