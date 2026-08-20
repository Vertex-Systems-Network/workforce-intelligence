<?php

/** Dependency-free Block J source smoke for runtime closure tooling and release scripts. */
$root = dirname(__DIR__);
$errors = [];
$mustContain = [
    'tools/run-runtime-closure.ps1' => ['runtime-closure', 'Tee-Object', 'WORKINTEL_RESET_CONFIRM', 'Protect-LogLine'],
    'tools/e2e-browser-doctor.mjs' => ['findBrowserExecutable', 'browser doctor'],
    'tools/runtime-closure-preflight.php' => ['phpunit_sqlite', 'php_ini_loaded_file', 'package-lock.json'],
    'tools/assert-disposable-database.php' => ['APP_ENV', 'production', 'WORKINTEL_ALLOW_DESTRUCTIVE_RESET'],
    'verify-clean-install.cmd' => ['runtime-closure-smoke.php', 'assert-disposable-database.php'],
    'verify-release.cmd' => ['runtime-closure-smoke.php'],
    'config/workintel.php' => ["trim((string) env('WORKINTEL_PLATFORM_OPERATOR_EMAILS', ''))", 'owner@acme.test'],
];
foreach ($mustContain as $file => $needles) {
    $path = $root.'/'.$file;
    if (! is_file($path)) { $errors[] = "Missing {$file}"; continue; }
    $source = file_get_contents($path) ?: '';
    foreach ($needles as $needle) if (! str_contains($source, $needle)) $errors[] = "{$file} missing {$needle}";
}
if ($errors) {
    fwrite(STDERR, "Block J runtime closure smoke failed:\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}
echo "Block J runtime closure smoke: PASS\n";
