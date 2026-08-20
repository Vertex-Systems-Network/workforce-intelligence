<?php

/**
 * Verifies that the four final Block N Laragon corrections are physically present in the active project tree.
 *
 * This check is dependency-free so it can run before Composer, Laravel or PHPUnit.
 */
$root = dirname(__DIR__);
$checks = [
    'tests/Unit/BlockNLaragonRegressionContractTest.php' => [
        "assertStringContainsString('tabs={['",
        "assertStringNotContainsString('<Tabs value={tab}",
        "assertStringNotContainsString('<Tabs value={inspectorTab}",
    ],
    'tests/Feature/ApprovalFlowTest.php' => [
        "manager@acme.test",
        "Sanctum::actingAs(\$manager)",
        "assertJsonPath('counts.inbox', 1)",
    ],
    'app/Http/Controllers/Api/V1/SchedulingController.php' => [
        "\$swap->load(['assignment.shift','requester.user','target.user'])",
        "syncExternalDecision('shift_swap_request'",
    ],
    'tests/Feature/WebsitePortalBuilderFlowTest.php' => [
        'Website Studio must provide a home page before public-domain delivery is tested.',
        "changePlan(\$member->workspace, 'platinum', 'monthly', false)",
        "public-websites/resolve?host=site.acme.example",
    ],
];

$forbidden = [
    'tests/Unit/BlockNLaragonRegressionContractTest.php' => [
        "assertStringNotContainsString('tabs={['",
        "assertStringContainsString('items={['",
    ],
    'app/Http/Controllers/Api/V1/SchedulingController.php' => [
        "\$swap->fresh()->load(['assignment.shift','requester.user','target.user'])",
    ],
];

$failures = [];
foreach ($checks as $relative => $needles) {
    $path = $root.'/'.$relative;
    if (! is_file($path)) {
        $failures[] = "MISSING {$relative}";
        continue;
    }
    $source = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (! str_contains($source, $needle)) $failures[] = "STALE {$relative} (missing current marker)";
    }
}
foreach ($forbidden as $relative => $needles) {
    $path = $root.'/'.$relative;
    if (! is_file($path)) continue;
    $source = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (str_contains($source, $needle)) $failures[] = "STALE {$relative} (previous-revision marker still present)";
    }
}

if ($failures) {
    fwrite(STDERR, "Block N final sync check: FAIL\n");
    foreach (array_values(array_unique($failures)) as $failure) fwrite(STDERR, " - {$failure}\n");
    fwrite(STDERR, "Extract the forced-sync patch over the project root before running PHPUnit.\n");
    exit(1);
}

echo "Block N final sync check: PASS\n";
foreach (array_keys($checks) as $relative) echo " - current: {$relative}\n";
