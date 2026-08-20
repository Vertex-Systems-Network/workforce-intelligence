<?php

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

/** Provides fake schema builder behavior within the WorkIntel application. */ final class FakeSchemaBuilder
{
    public array $created = [];
    public array $altered = [];
    /** Initializes the class with its required dependencies and state. */ public function __construct(private array $tables = [], private array $columns = [], private array $indexes = []) {}
    /** Determines whether the has table condition is satisfied. */ public function hasTable(string $table): bool { return in_array($table, $this->tables, true); }
    /** Determines whether the has column condition is satisfied. */ public function hasColumn(string $table, string $column): bool { return in_array($table.'.'.$column, $this->columns, true); }
    /** Determines whether the has index condition is satisfied. */ public function hasIndex(string $table, array|string $index): bool {
        $key = is_array($index) ? $table.'.'.implode(',', $index) : $table.'.'.$index;
        return in_array($key, $this->indexes, true);
    }
    /** Handles the table operation for the current WorkIntel workflow. */ public function table(string $table, callable $callback): void { $this->altered[] = $table; }
    /** Creates and persists the requested resource. */ public function create(string $table, callable $callback): void { $this->created[] = $table; if (! in_array($table, $this->tables, true)) $this->tables[] = $table; }
    /** Handles the drop if exists operation for the current WorkIntel workflow. */ public function dropIfExists(string $table): void {}
}

/** Handles the run migration operation for the current WorkIntel workflow. */ function runMigration(FakeSchemaBuilder $schema): FakeSchemaBuilder {
    $app = new Container();
    $app->instance('db.schema', $schema);
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    $migration = require __DIR__.'/../database/migrations/2026_08_11_001400_create_compensation_and_payroll_tables.php';
    $migration->up();
    return $schema;
}

$existing = runMigration(new FakeSchemaBuilder([], ['projects.completed_at']));
if (in_array('projects', $existing->altered, true)) {
    fwrite(STDERR, "FAIL: migration attempted to add projects.completed_at even though it already exists.\n");
    exit(1);
}
foreach (['compensation_profiles','payroll_runs','payroll_items','payroll_adjustments','payroll_item_projects','payroll_actions'] as $table) {
    if (! in_array($table, $existing->created, true)) {
        fwrite(STDERR, "FAIL: expected {$table} to continue creating after completed_at guard.\n");
        exit(1);
    }
}

$missing = runMigration(new FakeSchemaBuilder([], []));
if (! in_array('projects', $missing->altered, true)) {
    fwrite(STDERR, "FAIL: missing projects.completed_at was not scheduled for creation.\n");
    exit(1);
}

$partial = runMigration(new FakeSchemaBuilder(['compensation_profiles'], ['projects.completed_at']));
if (in_array('compensation_profiles', $partial->created, true)) {
    fwrite(STDERR, "FAIL: partially-created compensation_profiles table was not skipped.\n");
    exit(1);
}
if (! in_array('payroll_runs', $partial->created, true)) {
    fwrite(STDERR, "FAIL: recovery did not continue after pre-existing compensation_profiles.\n");
    exit(1);
}

echo "PASS: Milestone 9 migration is retry-safe for pre-existing completed_at and partial payroll DDL.\n";
