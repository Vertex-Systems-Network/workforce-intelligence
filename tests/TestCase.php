<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/** Base feature-test case that converts a missing SQLite driver into one clear environment skip instead of hundreds of false domain failures. */
abstract class TestCase extends BaseTestCase
{
    /** Skip database-backed feature assertions when the configured PHPUnit SQLite driver is unavailable. */
    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('WorkIntel feature tests require PHP pdo_sqlite because phpunit.xml uses SQLite :memory:. Enable pdo_sqlite/sqlite3 in the CLI PHP runtime.');
        }

        parent::setUp();
    }
}
