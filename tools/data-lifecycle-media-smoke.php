<?php
/** Runs dependency-free Block D source contracts before Composer or database boot. */
$root = dirname(__DIR__);
$checks = [
    'Block D migration' => 'database/migrations/2026_08_14_000100_create_data_lifecycle_and_media_library.php',
    'Lifecycle service' => 'app/Services/Lifecycle/DataLifecycleService.php',
    'Media service' => 'app/Services/Media/MediaLibraryService.php',
    'Media controller' => 'app/Http/Controllers/Api/V1/MediaController.php',
    'Trash controller' => 'app/Http/Controllers/Api/V1/DataLifecycleController.php',
    'Media Library page' => 'resources/js/pages/MediaLibrary.tsx',
    'Trash Center page' => 'resources/js/pages/TrashCenter.tsx',
    'Media Picker' => 'resources/js/media/MediaPicker.tsx',
    'Avatar Cropper' => 'resources/js/media/AvatarCropper.tsx',
];
foreach ($checks as $label => $relative) {
    if (! is_file($root.'/'.$relative)) {
        fwrite(STDERR, "FAIL: {$label} missing at {$relative}".PHP_EOL);
        exit(1);
    }
}
$lifecycle = file_get_contents($root.'/app/Services/Lifecycle/DataLifecycleService.php');
foreach (['client', 'project', 'task', 'media', 'media-folder'] as $type) {
    if (! str_contains($lifecycle, "'{$type}'")) exit("FAIL: lifecycle type {$type} missing".PHP_EOL);
}
$routes = file_get_contents($root.'/routes/api.php');
foreach (["'/media'", "'/trash'", "'/lifecycle/{type}/{id}/trash'"] as $needle) {
    if (! str_contains($routes, $needle)) exit("FAIL: route {$needle} missing".PHP_EOL);
}
$permissions = file_get_contents($root.'/app/Support/PermissionCatalog.php');
foreach (['media.view', 'media.manage', 'trash.view', 'trash.restore', 'trash.purge'] as $permission) {
    if (! str_contains($permissions, $permission)) exit("FAIL: permission {$permission} missing".PHP_EOL);
}
echo "Block D Data Lifecycle + Media smoke: PASS".PHP_EOL;
