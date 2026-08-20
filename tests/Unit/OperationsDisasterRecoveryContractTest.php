<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards Block K backup, restore-token, scheduler and seller-operations contracts. */
class OperationsDisasterRecoveryContractTest extends TestCase
{
    /** Assert schema, hash-only restore tokens, backup commands and seller routes exist. */
    public function test_operations_and_disaster_recovery_contract_is_wired(): void
    {
        $migration=file_get_contents(base_path('database/migrations/2026_08_14_000500_create_operations_disaster_recovery.php'));
        $service=file_get_contents(base_path('app/Services/Operations/SystemOperationsService.php'));
        $routes=file_get_contents(base_path('routes/commerce.php'));
        $console=file_get_contents(base_path('routes/console.php'));
        $seller=file_get_contents(base_path('resources/js/pages/SellerConsole.tsx'));
        $this->assertStringContainsString("system_backup_runs",$migration);
        $this->assertStringContainsString("system_restore_requests",$migration);
        $this->assertStringContainsString("hash('sha256',\$raw)",$service);
        $this->assertStringContainsString('minimum_verified_copies',$service);
        $this->assertStringContainsString("/operations/backups",$routes);
        $this->assertStringContainsString('workintel:backup-if-due',$console);
        $this->assertStringContainsString('Operations & Recovery',$seller);
    }
}
