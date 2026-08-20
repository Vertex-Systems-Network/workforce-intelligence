<?php

/** Verify DataGrid V2 source contracts without requiring Laravel to boot. */
$root = dirname(__DIR__);
$failures = [];

/** Record one dependency-free DataGrid source assertion. */
function gridAssert(bool $condition, string $message): void
{
    global $failures;
    if (! $condition) $failures[] = $message;
}

$package = json_decode((string) file_get_contents($root.'/package.json'), true);
$ui = (string) file_get_contents($root.'/resources/js/design-system/index.tsx');
$css = (string) file_get_contents($root.'/resources/js/design-system/toolkit.css');
$preferences = (string) file_get_contents($root.'/app/Http/Controllers/Api/V1/UserPagePreferenceController.php');
$server = (string) file_get_contents($root.'/app/Support/DataGridRequest.php');

gridAssert(($package['dependencies']['@tanstack/react-table'] ?? null) === '^8.21.3', 'Stable TanStack React Table v8 dependency is missing.');
foreach (['useReactTable', 'getCoreRowModel', 'getFilteredRowModel', 'getSortedRowModel', 'getPaginationRowModel', 'dataGridQueryParams', "t('common.saved_views')", 'bulkActions', 'manualPagination'] as $token) {
    gridAssert(str_contains($ui, $token), "DataGrid source is missing {$token}.");
}
foreach (['ui-data-grid-v2__filters', 'ui-data-grid-v2__pagination', 'ui-data-grid-v2__mobile', 'ui-data-grid-v2__bulk'] as $token) {
    gridAssert(str_contains($css, $token), "DataGrid CSS is missing {$token}.");
}
foreach (['settings.data_grid.sorting', 'settings.data_grid.filters', 'settings.data_grid.visibility', 'settings.data_grid.savedViews'] as $token) {
    gridAssert(str_contains($preferences, $token), "User preference validation is missing {$token}.");
}
foreach (['sortKeys', 'filterKeys', 'applySearch', 'applySorting', 'dateRange', 'in_array($id, $sortKeys, true)'] as $token) {
    gridAssert(str_contains($server, $token), "Server-side DataGrid contract is missing {$token}.");
}
foreach (['People.tsx', 'Clients.tsx', 'Projects.tsx', 'Tasks.tsx'] as $page) {
    $source = (string) file_get_contents($root.'/resources/js/pages/'.$page);
    gridAssert(str_contains($source, 'DataGrid'), "{$page} has not migrated to DataGrid V2.");
    gridAssert(str_contains($source, 'persistKey='), "{$page} does not persist its DataGrid state.");
}

if ($failures) {
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: {$failure}".PHP_EOL);
    exit(1);
}

echo "DataGrid V2 source smoke: PASS".PHP_EOL;
echo "Migrated high-use screens: 4".PHP_EOL;
echo "Server query contract: PASS".PHP_EOL;
