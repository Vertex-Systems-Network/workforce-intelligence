<?php

/** Dependency-free regression proving the bootstrap runtime guard recreates every writable Laravel directory. */
$project = dirname(__DIR__);
require_once $project.'/bootstrap/runtime.php';

$root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'workintel-runtime-smoke-'.bin2hex(random_bytes(6));
$required = [
    'bootstrap/cache',
    'storage/app/private',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
];

try {
    workintel_prepare_runtime_directories($root);
    foreach ($required as $directory) {
        $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);
        if (! is_dir($path) || ! is_writable($path)) {
            throw new RuntimeException("Runtime recovery smoke failed for {$directory}.");
        }
    }
    echo "Runtime directory recovery smoke: PASS".PHP_EOL;
} finally {
    /** Recursively remove one temporary smoke-test directory. */
    $remove = static function (string $path) use (&$remove): void {
        if (! is_dir($path)) return;
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $child = $path.DIRECTORY_SEPARATOR.$entry;
            is_dir($child) ? $remove($child) : @unlink($child);
        }
        @rmdir($path);
    };
    $remove($root);
}
