<?php

/**
 * Prepare writable Laravel runtime directories before Artisan is booted by Composer.
 *
 * The framework-independent bootstrap guard owns the canonical directory list so CLI,
 * Composer, and normal web requests recover the exact same runtime tree.
 */
$root = dirname(__DIR__);
require_once $root.'/bootstrap/runtime.php';

try {
    workintel_prepare_runtime_directories($root);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}

foreach ([
    'bootstrap/cache',
    'storage/app/private',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
] as $directory) {
    $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);
    $placeholder = $path.DIRECTORY_SEPARATOR.'.gitignore';
    if (! is_file($placeholder)) {
        @file_put_contents($placeholder, "*".PHP_EOL."!.gitignore".PHP_EOL);
    }
}

echo "WorkIntel Laravel runtime directories are ready.".PHP_EOL;
