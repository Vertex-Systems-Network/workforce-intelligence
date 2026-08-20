<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Protects semantic migration filenames and runtime table naming. */
class DatabaseSchemaNamingContractTest extends TestCase
{
    /** Verify phase/milestone terminology is never used as an actual runtime table prefix. */
    public function test_runtime_tables_use_semantic_names(): void
    {
        $files = glob(base_path('database/migrations/*.php')) ?: [];
        $tables = [];
        foreach ($files as $file) {
            $source = file_get_contents($file) ?: '';
            preg_match_all('/Schema::(?:create|table)\([\'\"]([^\'\"]+)[\'\"]/', $source, $matches);
            $tables = array_merge($tables, $matches[1] ?? []);
        }
        $this->assertNotEmpty($tables);
        foreach ($tables as $table) $this->assertDoesNotMatchRegularExpression('/^(?:(?:phase|milestone|block)_|[pm][0-9]+_)/i', $table);
    }

    /** Verify migration filenames are semantic rather than project-phase identifiers. */
    public function test_migration_filenames_use_semantic_names(): void
    {
        $files = glob(base_path('database/migrations/*.php')) ?: [];
        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/(?:^|_)(?:(?:phase|milestone|block)[0-9_\-]*|[pm][0-9]+(?:_|\.|$))/i', basename($file));
        }
        $this->assertFileExists(base_path('database/migrations/2026_08_11_001850_repair_operational_security_integration_tables.php'));
        $this->assertFileExists(base_path('database/migrations/2026_08_11_002810_repair_workforce_role_permissions.php'));
        $this->assertFileExists(base_path('database/migrations/2026_08_11_002820_create_data_retention_tombstones.php'));
        $this->assertFileExists(base_path('database/migrations/2026_08_12_000100_repair_stability_role_permissions.php'));
        $this->assertFileExists(base_path('database/migrations/2026_08_14_000700_normalize_legacy_migration_history.php'));
    }
}
