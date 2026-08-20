<?php

use Illuminate\Foundation\PackageManifest;

/**
 * Build Laravel's discovered-package manifest without console rendering.
 *
 * The standard `artisan package:discover` command renders terminal HTML through
 * Termwind, which requires ext-dom. Building the manifest directly keeps normal
 * Composer installation deterministic while the runtime doctor can separately
 * enforce extensions needed for the complete application and PHPUnit suite.
 */
$root = dirname(__DIR__);
$autoload = $root.'/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "vendor/autoload.php is missing; run composer install first.".PHP_EOL);
    exit(1);
}
require $autoload;
$app = require $root.'/bootstrap/app.php';
$app->make(PackageManifest::class)->build();
echo "Laravel package manifest discovered successfully.".PHP_EOL;
