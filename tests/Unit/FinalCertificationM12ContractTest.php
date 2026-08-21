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

        $windowsCi=(string)file_get_contents($root.'/.github/workflows/windows-certification.yml');
        foreach(['self-hosted','Windows','X64','pdo_sqlite, sqlite3, fileinfo, gd','Parse Laragon release certification script','System.Management.Automation.Language.Parser','Actual Chrome Edge Firefox accessibility certification','test:e2e:cross-browser'] as $marker)$this->assertStringContainsString($marker,$windowsCi);

        $laragonPreflight=(string)file_get_contents($root.'/tools/laragon-release-preflight.php');
        foreach(['PHP_OS_FAMILY','pdo_mysql','gd','DB_CONNECTION=mysql',"DB::connection('mysql')",'SELECT VERSION() AS version'] as $marker)$this->assertStringContainsString($marker,$laragonPreflight);

        $laragonRunner=(string)file_get_contents($root.'/tools/run-laragon-release.ps1');
        foreach(['Start-Transcript','Stop-Transcript','Add-Content','Get-Content -Path $logFile -Tail 1','WORKINTEL_REQUIRE_CROSS_BROWSER','e2e-browser-doctor.mjs --require-all','verify-release.cmd','LARAGON RELEASE CERTIFICATION PASSED'] as $marker)$this->assertStringContainsString($marker,$laragonRunner);
        $this->assertMatchesRegularExpression('/Stop-Transcript.*Add-Content.*Get-Content -Path \\$logFile -Tail 1/s',$laragonRunner);

        $laragonCmd=(string)file_get_contents($root.'/verify-laragon-release.cmd');
        $this->assertStringContainsString('run-laragon-release.ps1',$laragonCmd);

        $laragonDoc=(string)file_get_contents($root.'/docs/architecture/LARAGON_RELEASE_CERTIFICATION.md');
        foreach(['combined Windows + MySQL','verify-laragon-release.cmd','non-destructive','storage/logs/certification'] as $marker)$this->assertStringContainsString($marker,$laragonDoc);
    }
}
