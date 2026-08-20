<?php

/**
 * Bootstrap PHPUnit with a lightweight Laravel application instance.
 *
 * Source-contract unit tests use helpers such as base_path() but intentionally
 * do not boot the full framework or database. Creating the Application here
 * gives those helpers a valid project root. Feature tests still create and
 * bootstrap their own fresh application through Tests\TestCase.
 */
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
require $root.'/bootstrap/app.php';
