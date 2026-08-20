<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the M2 WorkIntel Design System promotion and release enforcement. */
class DesignSystemM2ContractTest extends TestCase
{
    /** Ensure the authoritative design-system files and low-level primitives exist without a parallel legacy UI folder. */
    public function test_design_system_is_single_source_of_truth(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertDirectoryExists($root.'/resources/js/design-system');
        $this->assertDirectoryDoesNotExist($root.'/resources/js/ui');
        $source = (string) file_get_contents($root.'/resources/js/design-system/index.tsx');
        foreach (['Pressable', 'Checkbox', 'Radio', 'ChoiceInput', 'HiddenFileInput', 'Image', 'ProgressBar', 'DataGrid', 'Modal', 'Tooltip', 'Box', 'Stack', 'Inline', 'Grid', 'Text', 'Form', 'Label', 'Option', 'Link'] as $component) {
            $this->assertStringContainsString($component, $source);
        }
        $this->assertFileExists($root.'/resources/js/design-system/tokens.css');
        $this->assertFileExists($root.'/docs/architecture/M2_COMPONENT_CATALOG.csv');
    }

    /** Ensure release and clean-install verification run the M2 architecture guard. */
    public function test_release_verification_enforces_design_system_audit(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root.'/tools/design-system-audit.mjs');
        $release = (string) file_get_contents($root.'/verify-release.cmd');
        $clean = (string) file_get_contents($root.'/verify-clean-install.cmd');
        $this->assertStringContainsString('design-system-audit.mjs', $release);
        $this->assertStringContainsString('design-system-audit.mjs', $clean);
    }
    /** Ensure Batch 2 reduced static layout debt and locks the visual-state/page-CSS contracts. */
    public function test_batch_two_layout_and_visual_state_ratchets_are_present(): void
    {
        $root = dirname(__DIR__, 2);
        $baseline = json_decode((string) file_get_contents($root.'/docs/architecture/M2_INLINE_STYLE_BASELINE.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(445, $baseline['previousBaseline']);
        $this->assertSame(0, $baseline['total']);
        $audit = (string) file_get_contents($root.'/tools/design-system-audit.mjs');
        $css = (string) file_get_contents($root.'/resources/js/design-system/toolkit.css');
        foreach (['allowedPageCss', 'aria-invalid', 'prefers-reduced-motion:reduce'] as $marker) {
            $this->assertStringContainsString($marker, $marker === 'allowedPageCss' ? $audit : $css);
        }
    }

}
