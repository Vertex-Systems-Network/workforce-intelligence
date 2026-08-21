<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Protects the repository-side branch-protection operator contract. */
class MainBranchProtectionContractTest extends TestCase
{
    public function test_main_branch_protection_operator_contract_is_present(): void
    {
        $root = base_path();
        $script = (string) file_get_contents($root.'/tools/apply-main-branch-protection.ps1');
        $cmd = (string) file_get_contents($root.'/apply-main-branch-protection.cmd');

        foreach ([
            "contexts = @('test', 'windows-certification')",
            'strict = $true',
            'enforce_admins = $true',
            'dismiss_stale_reviews = $true',
            'required_approving_review_count = 1',
            'required_conversation_resolution = $true',
            'allow_force_pushes = $false',
            'allow_deletions = $false',
            'repos/$Repository/branches/$Branch/protection',
            'MAIN BRANCH PROTECTION CERTIFICATION PASSED',
        ] as $marker) {
            $this->assertStringContainsString($marker, $script);
        }

        foreach (['where gh', 'apply-main-branch-protection.ps1'] as $marker) {
            $this->assertStringContainsString($marker, $cmd);
        }
    }
}
