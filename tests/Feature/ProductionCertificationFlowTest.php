<?php

namespace Tests\Feature;

use App\Support\ProductionCertificationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Verifies production readiness reflects current schema and doctor behavior. */
class ProductionCertificationFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Ensure current migrations satisfy the readiness schema and production doctor inventories. */
    public function test_readiness_and_production_doctor_accept_current_schema(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        foreach (ProductionCertificationCatalog::REQUIRED_TABLES as $table) $this->assertTrue(\Schema::hasTable($table), "Missing {$table}");
        $this->getJson('/health/ready')->assertOk()->assertJsonPath('ok', true);
        $exit = Artisan::call('workintel:production-doctor', ['--json' => true]);
        $this->assertSame(0, $exit, Artisan::output());
    }
}
