<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__);

/** Stop the target-workstation certification before any release mutation when a prerequisite is not true. */
function certificationFail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}".PHP_EOL);
    exit(1);
}

if (PHP_OS_FAMILY !== 'Windows') {
    certificationFail('Laragon release certification must run on the target Windows workstation.');
}

foreach (['.env', 'vendor/autoload.php', 'bootstrap/app.php'] as $requiredFile) {
    if (! is_file($root.DIRECTORY_SEPARATOR.$requiredFile)) {
        certificationFail("Missing required runtime file: {$requiredFile}");
    }
}

foreach (['pdo_mysql', 'gd'] as $extension) {
    if (! extension_loaded($extension)) {
        certificationFail("Required PHP extension is not loaded: {$extension}");
    }
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = (string) config('database.default');
if ($connection !== 'mysql') {
    certificationFail("Target workstation must use DB_CONNECTION=mysql; active connection is {$connection}.");
}

$config = (array) config('database.connections.mysql', []);
$database = trim((string) ($config['database'] ?? ''));
if ($database === '') {
    certificationFail('MySQL database name is empty in the active Laravel configuration.');
}

try {
    $db = DB::connection('mysql');
    $pdo = $db->getPdo();
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver !== 'mysql') {
        certificationFail("Expected PDO mysql driver; connected driver is {$driver}.");
    }

    $versionRow = $db->selectOne('SELECT VERSION() AS version');
    $databaseRow = $db->selectOne('SELECT DATABASE() AS database_name');
    $serverVersion = (string) ($versionRow->version ?? 'unknown');
    $connectedDatabase = (string) ($databaseRow->database_name ?? '');

    if ($connectedDatabase === '') {
        certificationFail('MySQL connection succeeded but no active database is selected.');
    }
} catch (Throwable $exception) {
    certificationFail('Unable to establish the configured MySQL connection: '.$exception->getMessage());
}

$phpBinary = PHP_BINARY;
$laragonPathHint = stripos($phpBinary, 'laragon') !== false ? 'detected' : 'not detected in PHP binary path';
$host = (string) ($config['host'] ?? '');
$port = (string) ($config['port'] ?? '');

echo 'WorkIntel Laragon target preflight: PASS'.PHP_EOL;
echo 'Windows: '.PHP_OS_FAMILY.PHP_EOL;
echo 'PHP: '.PHP_VERSION.' ('.$phpBinary.')'.PHP_EOL;
echo 'Laragon path hint: '.$laragonPathHint.PHP_EOL;
echo 'PDO driver: mysql'.PHP_EOL;
echo 'PHP extensions: pdo_mysql, gd'.PHP_EOL;
echo 'MySQL endpoint: '.$host.($port !== '' ? ':'.$port : '').PHP_EOL;
echo 'MySQL database: '.$connectedDatabase.PHP_EOL;
echo 'MySQL/MariaDB server: '.$serverVersion.PHP_EOL;
