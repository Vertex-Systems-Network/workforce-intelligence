<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Provides p0 frontend contract test behavior within the WorkIntel application. */ class FrontendFoundationContractTest extends TestCase
{
    /** Handles the test tasks read only view does not require project or people resources operation for the current WorkIntel workflow. */ public function test_tasks_read_only_view_does_not_require_project_or_people_resources(): void
    {
        $source = file_get_contents(__DIR__.'/../../resources/js/pages/Tasks.tsx');
        $this->assertStringContainsString("hasAnyPermission(workspace,['tasks.manage','tasks.manage_team'])", $source);
        $this->assertSame(1,preg_match('/if\s*\(canManage\)\s*\{/',$source));
        $this->assertStringContainsString('canManage&&', $source);
        $this->assertStringContainsString('New task', $source);
    }

    /** Handles the test screenshot interval is a number input starting at one minute operation for the current WorkIntel workflow. */ public function test_screenshot_interval_is_a_number_input_starting_at_one_minute(): void
    {
        $source = file_get_contents(__DIR__.'/../../resources/js/pages/Settings.tsx');
        $this->assertStringContainsString('type="number" min={limits.interval_min}', $source);
        $this->assertStringContainsString('interval_min:1', $source);
        $this->assertStringNotContainsString('<option value={5}>Every 5 min</option>', $source);
    }
}
