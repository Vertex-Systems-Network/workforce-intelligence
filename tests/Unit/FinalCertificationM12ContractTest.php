<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
/** Protects M12 accessibility, performance and final release certification source contracts. */
class FinalCertificationM12ContractTest extends TestCase
{
    /** Verifies final runtime budgets, build budgets and release orchestration remain registered. */
    public function test_m12_final_certification_contracts_are_present(): void
    {
        $root=base_path();$doctor=(string)file_get_contents($root.'/app/Console/Commands/FinalCertificationDoctor.php');$catalog=(string)file_get_contents($root.'/app/Support/FinalCertificationCatalog.php');$package=(string)file_get_contents($root.'/package.json');$ci=(string)file_get_contents($root.'/.github/workflows/ci.yml');
        foreach(['route_budget','scheduler_budget','frontend_build','no_vite_hot'] as $marker)$this->assertStringContainsString($marker,$doctor);
        foreach(['ROUTES_MIN','ROUTES_MAX','SCHEDULED_WORKINTEL_MAX'] as $marker)$this->assertStringContainsString($marker,$catalog);
        foreach(['performance:audit:build','audit:final-certification'] as $marker)$this->assertStringContainsString($marker,$package);
        foreach(['performance:audit:build','workintel:final-certification'] as $marker)$this->assertStringContainsString($marker,$ci);
        $production=(string)file_get_contents($root.'/app/Support/ProductionCertificationCatalog.php');
        foreach(['media_renditions','website_preview_tokens','document_brand_kits','document_batch_jobs','chat_activity_states'] as $marker)$this->assertStringContainsString($marker,$production);
    }
}
