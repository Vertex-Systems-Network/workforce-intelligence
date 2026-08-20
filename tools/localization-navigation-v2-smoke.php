<?php

/** Verify Localization & Navigation V2 source contracts without booting Laravel. */
$root = dirname(__DIR__);
$failures = [];
$domains = ['core', 'workforce', 'business', 'studios', 'collaboration', 'help'];
/** Record one Block E source assertion. */
function blockEAssert(bool $condition, string $message): void
{
    global $failures;
    if (! $condition) $failures[] = $message;
}
/** Extract one locale's keys from all six frontend domain modules. */
function blockELocaleKeys(string $root, array $domains, string $locale): array
{
    $keys = [];
    foreach ($domains as $domain) {
        $path = $root."/resources/js/i18n/locales/{$domain}.ts";
        if (! is_file($path)) continue;
        $title = ucfirst($domain);
        $source = (string) file_get_contents($path);
        if (! preg_match("/export const {$locale}{$title}=\\{([\\s\\S]*?)\\n\\} as const/", $source, $block)) continue;
        preg_match_all("/^\\s*'([^']+)'\\s*:/m", $block[1], $matches);
        array_push($keys, ...($matches[1] ?? []));
    }
    return $keys;
}

$englishList = blockELocaleKeys($root, $domains, 'en');
$english = array_values(array_unique($englishList));
blockEAssert(count($english) >= 300, 'Frontend translation catalog is unexpectedly small.');
blockEAssert(count($english) === count($englishList), 'English frontend translation keys contain cross-domain duplicates.');
sort($english);
foreach (['tr','ru','ur','ar'] as $locale) {
    $list = blockELocaleKeys($root, $domains, $locale);
    $keys = array_values(array_unique($list));
    blockEAssert(count($keys) === count($list), "Frontend {$locale} contains cross-domain duplicate keys.");
    sort($keys);
    blockEAssert($keys === $english, "Frontend {$locale} translation keys do not match English.");
}

$backendEnglish = include $root.'/lang/en/messages.php';
foreach (['tr','ru','ur','ar'] as $locale) {
    $pack = include $root."/lang/{$locale}/messages.php";
    blockEAssert(array_keys($pack) === array_keys($backendEnglish), "Backend {$locale} translation keys do not match English.");
}

$manifest = json_decode((string) file_get_contents($root.'/resources/js/navigation.manifest.json'), true);
blockEAssert(is_array($manifest), 'Navigation manifest is invalid JSON.');
foreach ($manifest ?: [] as $role => $groups) {
    $ids = [];
    foreach ($groups as $group) foreach ($group['items'] as $item) $ids[] = $item[0];
    blockEAssert(count($ids) === count(array_unique($ids)), "{$role} navigation contains duplicate IDs.");
    $scheduleCount = count(array_filter($ids, fn ($id) => $id === 'schedule'));
    blockEAssert($scheduleCount <= 1, "{$role} exposes duplicate Scheduling destinations.");
    if (in_array($role, ['employee','hr','manager','owner'], true)) blockEAssert($scheduleCount === 1, "{$role} should expose Scheduling.");
    blockEAssert(! in_array('shifts', $ids, true), "{$role} exposes the legacy duplicate Shift Templates destination.");
}

$sidebar = (string) file_get_contents($root.'/resources/js/components/Sidebar.tsx');
$context = (string) file_get_contents($root.'/resources/js/i18n/LocalizationContext.tsx');
$hub = (string) file_get_contents($root.'/resources/js/pages/SchedulingHub.tsx');
$ui = (string) file_get_contents($root.'/resources/js/design-system/index.tsx');
$toolkit = (string) file_get_contents($root.'/resources/js/design-system/toolkit.css');
foreach (['navigationForRole', 'key={group.id}', 'pageTranslationKey'] as $token) blockEAssert(str_contains($sidebar, $token), "Sidebar is missing {$token}.");
foreach (['workintel-language', 'document.documentElement.dir', 'document.body.dir', 'use_workspace_locale:false'] as $token) blockEAssert(str_contains($context, $token), "Localization context is missing {$token}.");
blockEAssert(! str_contains($context, 'refreshSession'), 'Language switching still refreshes the auth session and can race navigation rendering.');
foreach (["t('scheduling.board')", "t('scheduling.templates')", "'board'|'templates'"] as $token) blockEAssert(str_contains($hub, $token), "Scheduling hub is missing {$token}.");
foreach (["t('common.saved_views')", "t('common.rows_per_page')", "t('common.reset_table')"] as $token) blockEAssert(str_contains($ui, $token), "Shared DataGrid localization is missing {$token}.");
foreach (['border-inline-end', '[dir="rtl"] .ui-dropdown', '[dir="rtl"] .ui-select', '[dir="rtl"] .ui-data-grid-v2', '[dir="rtl"] .react-datepicker'] as $token) blockEAssert(str_contains($toolkit, $token), "RTL CSS is missing {$token}.");

if ($failures) {
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: {$failure}".PHP_EOL);
    exit(1);
}

echo 'Localization & Navigation V2 source smoke: PASS'.PHP_EOL;
echo 'Frontend core translation keys: '.count($english).PHP_EOL;
echo 'Frontend locale domain modules: '.count($domains).PHP_EOL;
echo 'Backend translation keys: '.count($backendEnglish).PHP_EOL;
echo 'Navigation roles checked: '.count($manifest ?: []).PHP_EOL;
