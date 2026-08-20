<?php
/** Performs dependency-free source checks for the consolidated WorkIntel release. */
$root = dirname(__DIR__);
$checks = [
    'Laravel entry point' => is_file($root.'/artisan'),
    'Composer lock' => is_file($root.'/composer.lock'),
    'Environment template' => is_file($root.'/.env.example'),
    'SQLite zero-install placeholder' => is_file($root.'/database/database.sqlite'),
    'React application entry' => is_file($root.'/resources/js/WorkforceApp.tsx'),
    'Task engine' => is_file($root.'/resources/js/pages/Tasks.tsx'),
    'Chat collaboration' => is_file($root.'/resources/js/pages/Chat.tsx'),
    'Chat enterprise collaboration' => is_file($root.'/app/Services/Chat/ChatEnterpriseCollaborationService.php') && is_file($root.'/resources/js/components/chat/EnterpriseControls.tsx'),
    'Chat performance certification' => is_file($root.'/app/Console/Commands/ChatPerformanceCertificationDoctor.php') && is_file($root.'/tools/chat-performance-certification-smoke.php'),
    'Seller console' => is_file($root.'/resources/js/pages/SellerConsole.tsx'),
    'Module catalog' => is_file($root.'/app/Support/ModuleCatalog.php'),
    'Document template engine' => is_file($root.'/app/Services/Documents/DocumentTemplateService.php'),
    'Screenshot storage service' => is_file($root.'/app/Services/ScreenshotStorage/ScreenshotStorageService.php'),
    'Commerce checkout service' => is_file($root.'/app/Services/Commerce/CommerceCheckoutService.php'),
    'Source integrity audit' => is_file($root.'/tools/audit-source-integrity.mjs'),
    'Database schema naming audit' => is_file($root.'/tools/database-schema-naming-audit.php'),
    'PHP documentation audit' => is_file($root.'/tools/audit-php-documentation.php'),
    'JS documentation audit' => is_file($root.'/tools/audit-js-documentation.mjs'),
    'Runtime closure tooling' => is_file($root.'/tools/runtime-closure-preflight.php') && is_file($root.'/tools/run-runtime-closure.ps1') && is_file($root.'/tools/e2e-browser-doctor.mjs'),
];
$failed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ').$label.PHP_EOL;
    if (! $ok) $failed++;
}
if ($failed) {
    fwrite(STDERR, "Release smoke failed with {$failed} issue(s).\n");
    exit(1);
}
echo "Release smoke passed.\n";
