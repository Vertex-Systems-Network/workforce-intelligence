<?php

/** Validate Block I production-certification source contracts without booting Laravel. */
$root = dirname(__DIR__);
$errors = [];
$mustContain = [
    'package.json' => ['test:e2e:public', 'test:e2e:full', '@playwright/test'],
    'tools/playwright.config.mjs' => ['tests/e2e', 'webServer', 'desktop', 'tablet', 'mobile'],
    'tools/run-browser-certification.mjs' => ['WORKINTEL_E2E_MODE', 'findBrowserExecutable'],
    'app/Console/Commands/ProductionCertificationDoctor.php' => ['workintel:production-doctor', 'REQUIRED_TABLES', 'REQUIRED_ROUTE_URIS'],
    'app/Services/System/ProductionHealthService.php' => ['ProductionCertificationCatalog::REQUIRED_TABLES'],
    'verify-release.cmd' => ['Production certification source smoke', 'Browser public certification'],
    'verify-clean-install.cmd' => ['Production certification source smoke', 'Browser full certification'],
    '.github/workflows/ci.yml' => ['playwright install', 'test:e2e:full'],
];
foreach ($mustContain as $relative => $needles) {
    $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (! is_file($path)) { $errors[] = "Missing {$relative}"; continue; }
    $text = (string) file_get_contents($path);
    foreach ($needles as $needle) if (! str_contains($text, $needle)) $errors[] = "{$relative} missing {$needle}";
}

$ui = (string) file_get_contents($root.'/resources/js/design-system/index.tsx');
if (! str_contains($ui, "window.addEventListener('scroll',update,true)")) $errors[] = 'Floating dropdown must reposition during nested scroll.';
if (preg_match('/addEventListener\([\'\"]scroll[\'\"].{0,120}setOpen\(false\)/s', $ui)) $errors[] = 'Dropdown/select must not close solely because the page scrolls.';
if (! str_contains($ui, 'ui-dropdown--portal')) $errors[] = 'Table action menus must use the body portal overlay.';

if ($errors) {
    fwrite(STDERR, "Block I production certification smoke failed:\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}
echo "Block I production certification smoke: PASS\n";
