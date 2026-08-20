<?php

/** Block N dependency-free source smoke for accessibility and cross-browser certification landmarks. */
$root = dirname(__DIR__);
$failures = [];
$checks = [
    'resources/js/design-system/accessibility.ts' => ['useFocusTrap', 'FOCUSABLE_SELECTOR'],
    'resources/js/design-system/index.tsx' => ['role="tablist"', 'aria-modal="true"', 'aria-sort'],
    'resources/css/app.css' => ['prefers-reduced-motion:reduce', '@media(pointer:coarse)', '@media(forced-colors:active)'],
    'tools/playwright.config.mjs' => ['accessibilityProjects', 'firefox-desktop', 'touch-mobile'],
    'tests/e2e/accessibility-platform.spec.mjs' => ['skip link reaches', 'command palette traps keyboard focus'],
];
foreach ($checks as $relative => $markers) {
    $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $body = is_file($path) ? (string) file_get_contents($path) : '';
    if ($body === '') { $failures[] = "Missing {$relative}"; continue; }
    foreach ($markers as $marker) if (! str_contains($body, $marker)) $failures[] = "{$relative} missing {$marker}";
}
if ($failures) {
    fwrite(STDERR, "WorkIntel Block N accessibility/browser smoke: FAIL\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}
echo "WorkIntel Block N accessibility/browser smoke: PASS\n";
