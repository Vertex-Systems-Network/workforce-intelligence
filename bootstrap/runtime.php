<?php

/**
 * Ensure Laravel's writable runtime directories exist before the framework boots.
 *
 * ZIP archives do not reliably preserve empty directories. This helper is intentionally
 * framework-independent so web requests, Artisan, and Composer can all self-heal the
 * required runtime tree before Laravel attempts to write sessions, compiled views,
 * cache data, logs, or bootstrap cache files.
 */
function workintel_prepare_runtime_directories(string $root): void
{
    $directories = [
        'bootstrap/cache',
        'storage/app/private',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
    ];

    foreach ($directories as $directory) {
        $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);

        if (! is_dir($path) && ! @mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException("Unable to create Laravel runtime directory: {$directory}");
        }

        if (! is_writable($path)) {
            @chmod($path, 0775);
        }

        if (! is_writable($path)) {
            throw new RuntimeException("Laravel runtime directory is not writable: {$directory}");
        }
    }
}
