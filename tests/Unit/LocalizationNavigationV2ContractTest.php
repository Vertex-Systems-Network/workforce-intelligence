<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Protects Localization & Navigation V2 architecture without requiring a database. */
class LocalizationNavigationV2ContractTest extends TestCase
{
    /** Verify navigation is immutable, translated and exposes one Scheduling destination. */
    public function test_navigation_uses_stable_manifest_and_single_scheduling_destination(): void
    {
        $manifest = json_decode((string) file_get_contents(base_path('resources/js/navigation.manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($manifest as $role => $groups) {
            $ids = collect($groups)->flatMap(fn (array $group) => collect($group['items'])->pluck(0))->all();
            $this->assertCount(count(array_unique($ids)), $ids, $role.' navigation must not contain duplicate IDs.');
            $scheduleCount = count(array_filter($ids, fn ($id) => $id === 'schedule'));
            $this->assertLessThanOrEqual(1, $scheduleCount);
            if (in_array($role, ['employee','hr','manager','owner'], true)) $this->assertSame(1, $scheduleCount);
            $this->assertNotContains('shifts', $ids);
        }
        $sidebar = file_get_contents(base_path('resources/js/components/Sidebar.tsx'));
        $this->assertStringContainsString('navigationForRole', $sidebar);
        $this->assertStringContainsString('key={group.id}', $sidebar);
    }

    /** Verify personal language switching is per-user and does not rebuild auth state. */
    public function test_locale_switch_is_per_user_and_rtl_aware_without_session_refresh(): void
    {
        $source = file_get_contents(base_path('resources/js/i18n/LocalizationContext.tsx'));
        foreach (['workintel-language', 'document.documentElement.dir', 'document.body.dir', 'use_workspace_locale:false'] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
        $this->assertStringNotContainsString('refreshSession', $source);
    }

    /** Verify legacy Shift Templates navigation is consolidated into the Scheduling hub. */
    public function test_scheduling_hub_contains_board_and_shift_templates(): void
    {
        $source = file_get_contents(base_path('resources/js/pages/SchedulingHub.tsx'));
        $this->assertStringContainsString("t('scheduling.board')", $source);
        $this->assertStringContainsString("t('scheduling.templates')", $source);
        $this->assertStringContainsString('<Scheduling/>', $source);
        $this->assertStringContainsString('<Shifts/>', $source);
    }

    /** Verify RTL overlays and tables use logical/directional CSS hooks. */
    public function test_rtl_css_covers_sidebar_overlays_tables_and_date_picker(): void
    {
        $source = file_get_contents(base_path('resources/js/design-system/toolkit.css'));
        foreach (['border-inline-end', '[dir="rtl"] .ui-dropdown', '[dir="rtl"] .ui-select', '[dir="rtl"] .ui-data-grid-v2', '[dir="rtl"] .react-datepicker'] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }
}
