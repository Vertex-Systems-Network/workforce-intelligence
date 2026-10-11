<?php

/**
 * Refuse destructive clean-install verification when the configured environment looks production-like.
 * This guard supplements, rather than replaces, the explicit RESET confirmation in the batch script.
 */
$root = dirname(__DIR__);
$path = $root.'/.env';
if (! is_file($path)) {
    fwrite(STDERR, "Block J safety guard: .env is missing. Create a disposable test configuration first.".PHP_EOL);
    exit(1);
}
$raw = file_get_contents($path) ?: '';
/** Read one dotenv value from the active .env file. */
$read = static function (string $key) use ($raw): string {
    if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $raw, $match)) return '';
    return trim(trim($match[1]), "\"'");
};
// The later Artisan command sees inherited process variables before dotenv values.
// A file-only approval is unsafe when another layer changes its actual database target.
// Reject all ambiguous target identity before any destructive command is reached.
foreach (['APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD', 'DB_SOCKET'] as $key) {
    $inherited = getenv($key);
    if ($inherited === false) {
        continue;
    }
    if (trim((string) $inherited) !== $read($key)) {
        fwrite(STDERR, "REFUSED: process environment overrides the approved .env database target selector {$key}.".PHP_EOL);
        exit(1);
    }
}
foreach (['DB_URL', 'DATABASE_URL'] as $key) {
    $inherited = getenv($key);
    if ($read($key) !== '' || ($inherited !== false && trim((string) $inherited) !== '')) {
        fwrite(STDERR, "REFUSED: database URL override {$key} makes the clean-install reset target ambiguous.".PHP_EOL);
        exit(1);
    }
}
if (is_file($root.'/bootstrap/cache/config.php')) {
    fwrite(STDERR, "REFUSED: cached Laravel configuration may override the approved disposable database target.".PHP_EOL);
    exit(1);
}
if ($read('APP_ENV') === '') {
    fwrite(STDERR, "REFUSED: APP_ENV must be explicitly configured for destructive verification.".PHP_EOL);
    exit(1);
}

$appEnv = strtolower($read('APP_ENV'));
$dbConnection = strtolower($read('DB_CONNECTION'));
$dbDatabase = $read('DB_DATABASE');
$override = getenv('WORKINTEL_ALLOW_DESTRUCTIVE_RESET') === '1';

if ($appEnv === 'production' && ! $override) {
    fwrite(STDERR, "REFUSED: verify-clean-install cannot reset a production APP_ENV. Use a disposable database/environment.".PHP_EOL);
    exit(1);
}
if ($dbConnection === '' || $dbDatabase === '') {
    fwrite(STDERR, "REFUSED: DB_CONNECTION/DB_DATABASE must be explicitly configured before destructive verification.".PHP_EOL);
    exit(1);
}
if ($dbConnection === 'sqlite') {
    $normalized = str_replace('\\', '/', $dbDatabase);
    if ($normalized !== ':memory:' && str_contains($normalized, '..') && ! $override) {
        fwrite(STDERR, "REFUSED: SQLite database path escapes the project tree. Set WORKINTEL_ALLOW_DESTRUCTIVE_RESET=1 only for an intentional disposable database.".PHP_EOL);
        exit(1);
    }
}

echo "Disposable database guard passed for APP_ENV={$appEnv}, DB_CONNECTION={$dbConnection}, DB_DATABASE=".basename(str_replace('\\', '/', $dbDatabase)).PHP_EOL;
