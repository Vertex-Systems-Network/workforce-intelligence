<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the locked modular-product architecture before UI migration starts. */
class ModuleArchitectureM1ContractTest extends TestCase
{
    /** Ensure the architecture contract classifies every workspace screen and special surface. */
    public function test_module_architecture_manifest_is_complete(): void
    {
        $path = dirname(__DIR__, 2).'/docs/architecture/workintel-modules.json';
        $this->assertFileExists($path);
        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(11, $manifest['modules']);
        $this->assertCount(40, $manifest['screenMap']);
        $this->assertSame('platform-console', $manifest['screenMap']['platform']['target']);
        $this->assertSame('MERGE', $manifest['screenMap']['shifts']['decision']);
        $this->assertSame('schedule', $manifest['screenMap']['shifts']['mergeInto']);
        $this->assertTrue($manifest['principles']['featurePagesUseDesignSystemOnly']);
    }

    /** Ensure release tooling runs the machine-readable module architecture audit. */
    public function test_release_verification_includes_module_architecture_audit(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root.'/tools/module-architecture-audit.mjs');
        $this->assertFileExists($root.'/tools/module-route-audit.php');
        $release = (string) file_get_contents($root.'/verify-release.cmd');
        $clean = (string) file_get_contents($root.'/verify-clean-install.cmd');
        $this->assertStringContainsString('module-architecture-audit.mjs', $release);
        $this->assertStringContainsString('module-route-audit.php', $release);
        $this->assertStringContainsString('module-architecture-audit.mjs', $clean);
        $this->assertStringContainsString('module-route-audit.php', $clean);
    }
}
