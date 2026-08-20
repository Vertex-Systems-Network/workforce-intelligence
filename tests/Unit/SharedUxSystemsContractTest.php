<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the M4 shared UX source and release contracts without requiring a database. */
final class SharedUxSystemsContractTest extends TestCase
{
    /** Verify the reusable M4 primitives and DataGrid V3 markers remain present. */
    public function test_shared_ux_primitives_and_data_grid_v3_are_present(): void
    {
        $source = file_get_contents(base_path('resources/js/design-system/index.tsx'));
        foreach (['FilterBar', 'DateRangeField', 'LoadingState', 'ErrorState', 'DialogActions', 'ConfirmDialog', 'ConfirmProvider', 'FormDialog', 'BooleanField', 'ChoiceList', 'ChoiceRow', 'SettingRow'] as $name) {
            self::assertStringContainsString("export function {$name}", $source);
        }
        self::assertStringContainsString('data-grid-version="3"', $source);
        self::assertStringContainsString('<DateRangeField', $source);
    }


    /** Verify Batch 2 removes native confirmation and DOM-click submit debt from feature pages. */
    public function test_batch_two_uses_app_confirmation_and_semantic_form_submission(): void
    {
        $root = base_path('resources/js/pages');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $nativeConfirmations = 0;
        $domClickSubmits = 0;

        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.tsx')) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            $nativeConfirmations += preg_match_all('/\b(?:window\.)?confirm\s*\(/', $source) ?: 0;
            $domClickSubmits += preg_match_all('/document\.getElementById\([^)]*\)\?*\.click\s*\(/', $source) ?: 0;
        }

        self::assertSame(0, $nativeConfirmations);
        self::assertSame(0, $domClickSubmits);
    }


    /** Verify closure migrations widen shared form and grid adoption without regressing legacy debt. */
    public function test_closure_widens_shared_form_grid_and_choice_adoption(): void
    {
        $root = base_path('resources/js/pages');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $formDialogs = 0;
        $dataGrids = 0;
        $choiceLists = 0;
        $settingRows = 0;
        $tableWraps = 0;

        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.tsx')) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            $formDialogs += preg_match_all('/<FormDialog\b/', $source) ?: 0;
            $dataGrids += preg_match_all('/<DataGrid\b/', $source) ?: 0;
            $choiceLists += preg_match_all('/<ChoiceList\b/', $source) ?: 0;
            $settingRows += preg_match_all('/<SettingRow\b/', $source) ?: 0;
            $tableWraps += preg_match_all('/<TableWrap\b/', $source) ?: 0;
        }

        self::assertGreaterThanOrEqual(12, $formDialogs);
        self::assertGreaterThanOrEqual(25, $dataGrids);
        self::assertGreaterThanOrEqual(3, $choiceLists);
        self::assertGreaterThanOrEqual(3, $settingRows);
        self::assertLessThanOrEqual(49, $tableWraps);
    }

    /** Verify release scripts keep the M4 shared UX audit mandatory. */
    public function test_release_gates_include_m4_shared_ux_audit(): void
    {
        self::assertStringContainsString('M4 Shared UX Systems audit', file_get_contents(base_path('verify-release.cmd')));
        self::assertStringContainsString('M4 Shared UX Systems audit', file_get_contents(base_path('verify-clean-install.cmd')));
    }
}
