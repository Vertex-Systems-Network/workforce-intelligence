<?php

/**
 * Run dependency-free source contracts for the UI/runtime stabilization release.
 */
$root = dirname(__DIR__);
$checks = [];

/** Register one source contract and whether it currently passes. */
function wiUiCheck(array &$checks, string $name, bool $ok): void
{
    $checks[] = [$name, $ok];
}

$composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
$postAutoload = $composer['scripts']['post-autoload-dump'] ?? [];
wiUiCheck($checks, 'runtime preparer exists', is_file($root.'/tools/prepare-runtime.php'));
wiUiCheck($checks, 'direct package manifest builder exists', is_file($root.'/tools/discover-packages.php'));
wiUiCheck($checks, 'composer prepares runtime before package discovery', ($postAutoload[0] ?? null) === '@php tools/prepare-runtime.php');
wiUiCheck($checks, 'composer avoids Termwind package:discover', ! str_contains(implode(' ', $postAutoload), 'artisan package:discover'));
wiUiCheck($checks, 'runtime directories are archived', is_file($root.'/storage/framework/views/.gitignore') && is_file($root.'/bootstrap/cache/.gitignore'));
wiUiCheck($checks, 'page preference migration exists', is_file($root.'/database/migrations/2026_08_13_000800_create_user_page_preferences.php'));
wiUiCheck($checks, 'page customization provider exists', is_file($root.'/resources/js/design-system/PageCustomization.tsx'));
wiUiCheck($checks, 'toast viewport exists', is_file($root.'/resources/js/design-system/toast.tsx'));
wiUiCheck($checks, 'dashboard builder exists', is_file($root.'/resources/js/components/DashboardGrid.tsx'));

$ui = (string) file_get_contents($root.'/resources/js/design-system/index.tsx');
$dashboard = (string) file_get_contents($root.'/resources/js/components/DashboardGrid.tsx');
$pageCustomization = (string) file_get_contents($root.'/resources/js/design-system/PageCustomization.tsx');
$api = (string) file_get_contents($root.'/resources/js/api/client.ts');
wiUiCheck($checks, 'single select uses React portal listbox', str_contains($ui, 'ui-select-menu') && str_contains($ui, 'createPortal'));
wiUiCheck($checks, 'file inputs use styled shared control', str_contains($ui, 'ui-file-input__action'));
wiUiCheck($checks, 'dashboard supports widget visibility', str_contains($dashboard, 'visible_widgets') && str_contains($dashboard, 'Manage dashboard widgets'));
wiUiCheck($checks, 'dashboard uses explicit edit mode', str_contains($dashboard, 'disableDrag: !editing') && str_contains($dashboard, 'Edit layout'));
wiUiCheck($checks, 'page preferences save to backend', str_contains($pageCustomization, '/api/v1/ui/preferences/'));
wiUiCheck($checks, 'API errors emit dismissible toast events', str_contains($api, 'emitToast'));
wiUiCheck($checks, 'floating overlays reposition during scroll', str_contains($ui, 'usePortalPosition') && str_contains($ui, "window.addEventListener('scroll',update,true)"));
wiUiCheck($checks, 'table action menus render in body portal', str_contains($ui, 'ui-dropdown--portal') && str_contains($ui, 'document.body'));
wiUiCheck($checks, 'shared form rhythm exists', str_contains($ui, 'FormSection') && str_contains($ui, 'FormGrid') && str_contains($ui, 'FormActions'));
wiUiCheck($checks, 'refresh feedback primitive exists', str_contains($ui, 'RefreshButton') && str_contains($ui, "t('common.refreshing')"));
wiUiCheck($checks, 'DataGrid V2 foundation exists', str_contains($ui, 'DataGrid') && str_contains($ui, 'Visible columns') && str_contains($ui, 'pageSizeOptions'));
wiUiCheck($checks, 'People table uses DataGrid V2', str_contains((string) file_get_contents($root.'/resources/js/pages/People.tsx'), '<DataGrid rows={filtered}'));
wiUiCheck($checks, 'feature tests guard missing sqlite driver once', str_contains((string) file_get_contents($root.'/tests/TestCase.php'), 'markTestSkipped') && str_contains((string) file_get_contents($root.'/tools/runtime-preflight.php'), 'pdo_sqlite'));

$failed = array_values(array_filter($checks, fn (array $check) => ! $check[1]));
foreach ($checks as [$name, $ok]) echo sprintf('[%s] %s%s', $ok ? 'PASS' : 'FAIL', $name, PHP_EOL);
if ($failed) {
    fwrite(STDERR, count($failed).' UI/runtime stabilization smoke check(s) failed.'.PHP_EOL);
    exit(1);
}
echo 'UI/runtime stabilization smoke passed.'.PHP_EOL;
