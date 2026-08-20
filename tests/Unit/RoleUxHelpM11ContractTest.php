<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
/** Protects M11 role UX, contextual help and onboarding contracts without a database. */
class RoleUxHelpM11ContractTest extends TestCase
{
    /** Verifies global help, role-aware guide and persistent preference contracts remain wired. */
    public function test_m11_global_help_and_role_onboarding_contracts_are_present(): void
    {
        $root=base_path();$shell=(string)file_get_contents($root.'/resources/js/WorkforceApp.tsx');$help=(string)file_get_contents($root.'/resources/js/components/HelpCenter.tsx');$catalog=(string)file_get_contents($root.'/resources/js/help/roleHelpCatalog.ts');$prefs=(string)file_get_contents($root.'/app/Http/Controllers/Api/V1/UserPagePreferenceController.php');
        $locale=(string)file_get_contents($root.'/resources/js/i18n/catalog.ts');$firstRun=(string)file_get_contents($root.'/resources/js/components/FirstRunGuide.tsx');$design=(string)file_get_contents($root.'/resources/js/design-system/index.tsx');$css=(string)file_get_contents($root.'/resources/js/design-system/toolkit.css');
        foreach(['HelpCenter','F1','setHelpOpen','workintel:open-help','FirstRunGuide'] as $token)$this->assertStringContainsString($token,$shell);
        foreach(["t('help.this_page')","t('help.start_here')","t('help.role_handbook')","t('help.search_aria')"] as $token)$this->assertStringContainsString($token,$help);
        foreach(['canAccessPage','isPageVisibleInNavigation','inferredGuideKey'] as $token)$this->assertStringContainsString($token,$catalog);
        foreach(['settings.onboarding_completed','settings.help_seen','settings.checklist_version'] as $token)$this->assertStringContainsString($token,$prefs);
        foreach(['Yardım Merkezi','Центр помощи','مدد مرکز','مركز المساعدة'] as $token)$this->assertStringContainsString($token,$locale);
        foreach(['workintel:open-help','first-run'] as $token)$this->assertStringContainsString($token,$firstRun);
        $this->assertStringContainsString('contextualHelp',$design);$this->assertStringContainsString('[dir="rtl"] .ui-help-directional-icon',$css);
    }
}
