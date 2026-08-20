<?php

/**
 * Perform dependency-free Block J runtime checks before Composer, Artisan, PHPUnit or npm gates run.
 *
 * The report deliberately avoids printing secrets. It is safe to attach to a support ticket.
 */
$root = dirname(__DIR__);
$json = in_array('--json', $argv, true);
$strict = in_array('--strict', $argv, true);
$checks = [];
$failures = 0;
$warnings = 0;

/** Record one runtime-closure check without exposing secret environment values. */
function checkRow(string $key, bool $ok, string $detail, bool $warning = false): void
{
    global $checks, $failures, $warnings;
    $checks[$key] = ['ok' => $ok, 'warning' => $warning && ! $ok, 'detail' => $detail];
    if (! $ok) {
        if ($warning) $warnings++;
        else $failures++;
    }
}

/** Read a dotenv key without loading Laravel or exposing unrelated values. */
function envValue(string $root, string $key): ?string
{
    $path = is_file($root.'/.env') ? $root.'/.env' : $root.'/.env.example';
    if (! is_file($path)) return null;
    $raw = file_get_contents($path) ?: '';
    if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $raw, $match)) return null;
    return trim(trim($match[1]), "\"'");
}

/** Execute a version command and return trimmed output without throwing. */
function commandVersion(string $command): string
{
    $output = @shell_exec($command.' 2>&1');
    return trim((string) $output);
}

checkRow('php_version', version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP '.PHP_VERSION.'; WorkIntel requires PHP >= 8.3.');
checkRow('php_ini', (bool) php_ini_loaded_file(), php_ini_loaded_file() ?: 'No php.ini loaded.');
foreach (['ctype','filter','hash','mbstring','openssl','session','tokenizer','fileinfo','dom','xml','xmlwriter','pdo'] as $extension) {
    checkRow('php_ext_'.$extension, extension_loaded($extension), extension_loaded($extension) ? "ext-{$extension} loaded." : "Missing ext-{$extension}.");
}
$pdoDrivers = class_exists(PDO::class) ? PDO::getAvailableDrivers() : [];
checkRow('pdo_driver', $pdoDrivers !== [], $pdoDrivers ? 'PDO drivers: '.implode(', ', $pdoDrivers) : 'PDO is enabled but no PDO driver is available.');
checkRow('phpunit_sqlite', in_array('sqlite', $pdoDrivers, true) && extension_loaded('sqlite3'), 'PHPUnit uses SQLite :memory:, requiring pdo_sqlite + sqlite3.');

$composer = commandVersion('composer --version');
checkRow('composer', $composer !== '' && ! preg_match('/not found|not recognized/i', $composer), $composer ?: 'Composer not found.');
$node = commandVersion('node --version');
$nodeVersion = ltrim($node, 'vV');
$nodeOk = $nodeVersion !== '' && version_compare($nodeVersion, '20.19.0', '>=') && !(version_compare($nodeVersion, '21.0.0', '>=') && version_compare($nodeVersion, '22.12.0', '<'));
checkRow('node', $nodeOk, $node ?: 'Node.js not found; package engines require ^20.19 or >=22.12.');
$npm = commandVersion('npm --version');
checkRow('npm', $npm !== '' && ! preg_match('/not found|not recognized/i', $npm), $npm ?: 'npm not found.');

checkRow('composer_lock', is_file($root.'/composer.lock'), is_file($root.'/composer.lock') ? 'composer.lock present.' : 'composer.lock missing.');
$packageLock = is_file($root.'/package-lock.json');
checkRow('package_lock', $packageLock, $packageLock ? 'package-lock.json present.' : 'package-lock.json is absent; npm install can run, but dependency resolution is not deterministic.', ! $strict);
checkRow('env_example', is_file($root.'/.env.example'), '.env.example '.(is_file($root.'/.env.example') ? 'present.' : 'missing.'));

$appEnv = strtolower((string) (envValue($root, 'APP_ENV') ?: 'unknown'));
$dbConnection = strtolower((string) (envValue($root, 'DB_CONNECTION') ?: 'unknown'));
$dbDatabase = (string) (envValue($root, 'DB_DATABASE') ?: '');
checkRow('app_environment', $appEnv !== 'unknown', 'APP_ENV='.$appEnv.'.');
checkRow('database_config', $dbConnection !== 'unknown' && $dbDatabase !== '', 'DB connection='.$dbConnection.'; database='.($dbDatabase !== '' ? basename(str_replace('\\', '/', $dbDatabase)) : '(missing)').'.');
if ($dbConnection === 'mysql') checkRow('pdo_mysql', in_array('mysql', $pdoDrivers, true), 'DB_CONNECTION=mysql requires pdo_mysql.');
if ($dbConnection === 'pgsql') checkRow('pdo_pgsql', in_array('pgsql', $pdoDrivers, true), 'DB_CONNECTION=pgsql requires pdo_pgsql.');
if ($dbConnection === 'sqlite') checkRow('pdo_sqlite_runtime', in_array('sqlite', $pdoDrivers, true), 'DB_CONNECTION=sqlite requires pdo_sqlite.');

foreach (['bootstrap/cache','storage/framework/views','storage/framework/sessions','storage/framework/cache/data','storage/logs'] as $directory) {
    $path = $root.'/'.$directory;
    checkRow('dir_'.str_replace(['/','-'], '_', $directory), is_dir($path) && is_writable($path), $directory.(is_dir($path) ? (is_writable($path) ? ' writable.' : ' is not writable.') : ' missing.'));
}

$result = [
    'ok' => $failures === 0,
    'strict' => $strict,
    'failures' => $failures,
    'warnings' => $warnings,
    'php_binary' => PHP_BINARY,
    'php_ini' => php_ini_loaded_file() ?: null,
    'checks' => $checks,
];

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} else {
    echo "WorkIntel Block J Runtime Closure Preflight\n=========================================\n";
    foreach ($checks as $name => $check) {
        $tag = $check['ok'] ? 'PASS' : ($check['warning'] ? 'WARN' : 'FAIL');
        echo sprintf("[%s] %-28s %s\n", $tag, $name, $check['detail']);
    }
    echo "\nResult: {$failures} failure(s), {$warnings} warning(s).\n";
    if (! $packageLock) echo "Tip: run npm install once on a networked certification machine and commit package-lock.json for deterministic npm ci.\n";
}
exit($failures > 0 ? 1 : 0);
