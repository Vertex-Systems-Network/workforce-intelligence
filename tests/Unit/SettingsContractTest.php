<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Provides p3 settings contract test behavior within the WorkIntel application. */ class SettingsContractTest extends TestCase
{
    /** Handles the test settings center exposes required workspace controls operation for the current WorkIntel workflow. */ public function test_settings_center_exposes_required_workspace_controls(): void
    {
        $source=file_get_contents(__DIR__.'/../../resources/js/pages/Settings.tsx');
        foreach(['Workspace name','Company name','Legal name','Timezone','Currency','Default language','Date format','Time format','Fiscal year starts','Number format','App title','Accent color','Logo','Favicon'] as $label) {
            $this->assertStringContainsString($label,$source);
        }
        $this->assertTrue(str_contains($source,'My Preferences') || str_contains($source,"t('settings.personal')"));
    }

    /** Handles the test custom role slug is not collapsed to employee in auth mapper operation for the current WorkIntel workflow. */ public function test_custom_role_slug_is_not_collapsed_to_employee_in_auth_mapper(): void
    {
        $source=file_get_contents(__DIR__.'/../../resources/js/auth/authService.ts');
        $this->assertStringContainsString('role: workspace.role as WorkspaceRole',$source);
        $this->assertStringNotContainsString("? workspace.role as WorkspaceRole\n      : 'employee'",$source);
    }
}
