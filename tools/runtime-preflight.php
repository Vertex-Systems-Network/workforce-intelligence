<?php

/**
 * Validate the PHP runtime before destructive migrations or PHPUnit are started.
 * This emits actionable failures instead of allowing Composer/Artisan to fail later.
 */
$required = ['ctype', 'filter', 'hash', 'mbstring', 'openssl', 'session', 'tokenizer', 'fileinfo'];
$testRequired = ['dom', 'xml', 'xmlwriter'];
$pdoDrivers = class_exists(PDO::class) ? PDO::getAvailableDrivers() : [];
$errors = [];

foreach ($required as $extension) {
    if (! extension_loaded($extension)) $errors[] = "Missing required PHP extension: ext-{$extension}";
}
foreach ($testRequired as $extension) {
    if (! extension_loaded($extension)) $errors[] = "Missing PHPUnit PHP extension: ext-{$extension}";
}
if (! extension_loaded('pdo')) $errors[] = 'Missing required PHP extension: ext-pdo';
if (! $pdoDrivers) $errors[] = 'PDO is enabled but no database driver is available (enable pdo_mysql or pdo_sqlite).';
if (! in_array('sqlite', $pdoDrivers, true)) $errors[] = 'PHPUnit is configured for SQLite :memory:, so ext-pdo_sqlite/sqlite3 must be enabled for feature tests.';

if ($errors) {
    fwrite(STDERR, "WorkIntel runtime preflight failed:".PHP_EOL.' - '.implode(PHP_EOL.' - ', $errors).PHP_EOL);
    fwrite(STDERR, "Laragon: enable the matching extensions in Menu > PHP > Extensions, then restart the terminal/web server.".PHP_EOL);
    exit(1);
}

echo 'WorkIntel PHP runtime preflight passed. PHP binary: '.PHP_BINARY.'; ini: '.(php_ini_loaded_file() ?: '(none)').'; PDO drivers: '.implode(', ', $pdoDrivers).PHP_EOL;
