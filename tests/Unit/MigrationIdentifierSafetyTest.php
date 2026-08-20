<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Provides migration identifier safety test behavior within the WorkIntel application. */ class MigrationIdentifierSafetyTest extends TestCase
{
    /** Handles the test payroll migration uses mysql safe compensation index name operation for the current WorkIntel workflow. */ public function test_payroll_migration_uses_mysql_safe_compensation_index_name(): void
    {
        $file = dirname(__DIR__, 2).'/database/migrations/2026_08_11_001400_create_compensation_and_payroll_tables.php';
        $source = file_get_contents($file);

        $this->assertIsString($source);
        $this->assertStringContainsString("'cp_ws_member_effective_idx'", $source);
        $this->assertStringNotContainsString(
            "compensation_profiles_workspace_id_member_id_effective_from_index",
            $source
        );
        $this->assertLessThanOrEqual(64, strlen('cp_ws_member_effective_idx'));
    }
}
