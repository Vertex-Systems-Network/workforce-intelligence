<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Protects the E.1 deep-page localization bridge without requiring a database. */
class LocalizationPageCopyE1ContractTest extends TestCase
{
    /** Verify the bridge is mounted and cannot mutate submitted field values or marked business data. */
    public function test_bridge_preserves_business_data_and_form_values(): void
    {
        $app = (string) file_get_contents(base_path('resources/js/app.tsx'));
        $bridge = (string) file_get_contents(base_path('resources/js/i18n/LegacyLocalizationBridge.tsx'));
        $this->assertStringContainsString('LegacyLocalizationBridge', $app);
        $this->assertStringContainsString('new WeakMap<Text,TextState>()', $bridge);
        $this->assertStringContainsString('[data-business-value="true"]', $bridge);
        $this->assertStringContainsString('[data-no-auto-i18n="true"]', $bridge);
        $this->assertStringNotContainsString('.value=', $bridge);
    }

    /** Verify canonical localization falls back to the registered deep-page copy layer. */
    public function test_catalog_uses_page_copy_fallback(): void
    {
        $catalog = (string) file_get_contents(base_path('resources/js/i18n/catalog.ts'));
        $copy = (string) file_get_contents(base_path('resources/js/i18n/pageCopy.ts'));
        $this->assertStringContainsString("import { translatePageCopy } from './pageCopy'", $catalog);
        $this->assertStringContainsString('translatePageCopy(locale,value)', $catalog);
        foreach (['core', 'workforce', 'business', 'studios', 'collaboration', 'help'] as $domain) {
            $this->assertStringContainsString("./page-copy/{$domain}", $copy);
            $this->assertFileExists(base_path("resources/js/i18n/page-copy/{$domain}.ts"));
        }
    }

    /** Verify representative deep-module phrases are localized while technical examples stay literal. */
    public function test_deep_page_copy_and_technical_literal_boundaries_are_registered(): void
    {
        $copy = (string) file_get_contents(base_path('resources/js/i18n/pageCopy.ts'));
        foreach (['core', 'workforce', 'business', 'studios', 'collaboration', 'help'] as $domain) {
            $copy .= PHP_EOL.(string) file_get_contents(base_path("resources/js/i18n/page-copy/{$domain}.ts"));
        }
        $copy .= PHP_EOL.(string) file_get_contents(base_path('resources/js/i18n/page-copy/core-phrases.ts'));
        foreach ([
            'Activity ≠ Productivity.',
            'Create a single- or multiple-choice poll for this conversation.',
            'Attribute access policy',
            'Trash is empty',
            'Storage health',
            'Approved Work Locations',
            'Saved only for your account in this workspace.',
        ] as $phrase) {
            $this->assertStringContainsString($phrase, $copy);
        }
        foreach (['/help', 'payload.status', 'from:12', 'before:2026-08-01'] as $literal) {
            $this->assertStringNotContainsString("'{$literal}':copy(", $copy);
        }
    }
}
