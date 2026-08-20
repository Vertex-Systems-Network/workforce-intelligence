<?php
namespace Tests\Feature;
use App\Support\FinalCertificationCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
/** Executes the M12 final certification doctor against a seeded target-style database. */
class FinalCertificationM12FlowTest extends TestCase
{
    use RefreshDatabase;
    /** Seed canonical WorkIntel data before exercising final runtime certification. */
    protected function setUp(): void { parent::setUp(); $this->seed(DatabaseSeeder::class); }
    /** Ensures final certification succeeds when schema/runtime landmarks satisfy bounded release budgets. */
    public function test_final_certification_doctor_accepts_seeded_runtime_without_requiring_a_frontend_build(): void
    {
        $routes=count(Route::getRoutes());$this->assertGreaterThanOrEqual(FinalCertificationCatalog::ROUTES_MIN,$routes);$this->assertLessThanOrEqual(FinalCertificationCatalog::ROUTES_MAX,$routes);
        $this->artisan('workintel:final-certification',['--json'=>true])->assertExitCode(0);
    }
}
