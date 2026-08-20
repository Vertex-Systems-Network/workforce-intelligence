<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Protects Block N shared accessibility and browser-certification source contracts. */
class AccessibilityBrowserCertificationContractTest extends TestCase
{
    #[Test]
    /** Ensure focus traps and shared semantic primitives remain wired into the toolkit. */
    public function shared_accessibility_primitives_are_present(): void
    {
        $focus = (string) file_get_contents(base_path('resources/js/design-system/accessibility.ts'));
        $ui = (string) file_get_contents(base_path('resources/js/design-system/index.tsx'));
        $this->assertStringContainsString('useFocusTrap', $focus);
        $this->assertStringContainsString('returnFocusRef', $focus);
        $this->assertStringContainsString('aria-modal="true"', $ui);
        $this->assertStringContainsString('role="tablist"', $ui);
        $this->assertStringContainsString('aria-sort', $ui);
    }

    #[Test]
    /** Ensure the responsive accessibility browser matrix retains Firefox, reflow and touch coverage. */
    public function playwright_accessibility_matrix_is_registered(): void
    {
        $config = (string) file_get_contents(base_path('tools/playwright.config.mjs'));
        $runner = (string) file_get_contents(base_path('tools/run-browser-certification.mjs'));
        $this->assertStringContainsString('firefox-desktop', $config);
        $this->assertStringContainsString('reflow-200pct-equivalent', $config);
        $this->assertStringContainsString('touch-mobile', $config);
        $this->assertStringContainsString('--require-system-browsers', $runner);
    }
}
