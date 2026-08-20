<?php

/** Verify E.1 full-page localization source contracts without booting Laravel. */
$root = dirname(__DIR__);
$failures = [];
/** Record one E.1 source assertion. */
function e1Assert(bool $condition, string $message): void
{
    global $failures;
    if (! $condition) $failures[] = $message;
}
/** Read the page-copy barrel plus all six domain registries. */
function e1PageCopySource(string $root): string
{
    $source = (string) file_get_contents($root.'/resources/js/i18n/pageCopy.ts');
    foreach (['core','workforce','business','studios','collaboration','help'] as $domain) {
        $source .= PHP_EOL.(string) file_get_contents($root."/resources/js/i18n/page-copy/{$domain}.ts");
    }
    $source .= PHP_EOL.(string) file_get_contents($root.'/resources/js/i18n/page-copy/core-phrases.ts');
    return $source;
}

$barrel = (string) file_get_contents($root.'/resources/js/i18n/pageCopy.ts');
$copy = e1PageCopySource($root);
$bridge = (string) file_get_contents($root.'/resources/js/i18n/LegacyLocalizationBridge.tsx');
$catalog = (string) file_get_contents($root.'/resources/js/i18n/catalog.ts');
$app = (string) file_get_contents($root.'/resources/js/app.tsx');

e1Assert(str_contains($app, 'LegacyLocalizationBridge'), 'Legacy localization bridge is not mounted.');
e1Assert(str_contains($catalog, 'translatePageCopy(locale,value)'), 'Canonical catalog does not fall back to page-copy translation.');
foreach (['translatePageCopy', 'hasPageCopyTranslation', 'pageCopyPhrases', 'pageCopyTerms'] as $token) {
    e1Assert(str_contains($barrel, $token), "Page copy translator is missing {$token}.");
}
foreach (['core','workforce','business','studios','collaboration','help'] as $domain) {
    e1Assert(str_contains($barrel, "./page-copy/{$domain}"), "Page copy barrel is missing {$domain} domain ownership.");
}
foreach (['new WeakMap<Text,TextState>()', 'new WeakMap<Element,Map<string,AttrState>>()', '[data-business-value="true"]', '[data-no-auto-i18n="true"]', '[contenteditable="true"]', 'pre, code, script, style'] as $token) {
    e1Assert(str_contains($bridge, $token), "Localization bridge is missing safety token {$token}.");
}
e1Assert(! str_contains($bridge, '.value='), 'Localization bridge can write form values.');

$critical = [
    'Activity ≠ Productivity.',
    'Top Applications & Websites',
    'Create a single- or multiple-choice poll for this conversation.',
    'Attribute access policy',
    'One reporting layer across time, attendance, payroll, activity, projects and people.',
    'No approval workflow history for this week yet.',
    'Trash is empty',
    'Reset your password',
    'Storage health',
    'Approved Work Locations',
    'Govern external access, retention, Legal hold, eDiscovery exports and DLP for workplace chat.',
    'Saved only for your account in this workspace.',
];
foreach ($critical as $phrase) e1Assert(str_contains($copy, $phrase), "Critical deep-page phrase is not registered: {$phrase}");
foreach (['/help', 'payload.status', 'from:12', 'before:2026-08-01'] as $literal) {
    e1Assert(! str_contains($copy, "'{$literal}':copy("), "Technical literal was incorrectly registered for translation: {$literal}");
}

e1Assert(strlen($barrel) < 15000, 'pageCopy.ts has regressed into a monolithic translation source.');

if ($failures) {
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: {$failure}".PHP_EOL);
    exit(1);
}

echo 'Localization Full Page Copy E.1 source smoke: PASS'.PHP_EOL;
echo 'Page-copy domain registries: 6'.PHP_EOL;
echo 'Business-data/form-value safety: PASS'.PHP_EOL;
echo 'Technical-literal boundary: PASS'.PHP_EOL;
