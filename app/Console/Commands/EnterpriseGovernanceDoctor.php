<?php

namespace App\Console\Commands;

use App\Models\EnterpriseIdentityProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Provides enterprise governance doctor behavior within the WorkIntel application. */ class EnterpriseGovernanceDoctor extends Command
{
    protected $signature = 'workintel:enterprise-doctor';
    protected $description = 'Validate Phase 23 enterprise identity, SCIM, MFA and governance readiness';

    /** Executes the command, job, or request handler. */ public function handle(): int
    {
        $tables = [
            'enterprise_identity_providers', 'enterprise_sso_states', 'scim_access_tokens', 'user_mfa_methods',
            'workspace_security_policies', 'workspace_ip_rules', 'workspace_access_sessions', 'workspace_access_policies',
            'legal_entities', 'business_units', 'data_governance_policies', 'data_retention_runs', 'data_retention_tombstones',
        ];
        $bad = 0;
        foreach ($tables as $table) {
            $ok = Schema::hasTable($table);
            $this->line(($ok ? '[OK] ' : '[MISSING] ').$table);
            if (! $ok) $bad++;
        }

        foreach (['legal_entity_id', 'business_unit_id'] as $column) {
            $ok = Schema::hasColumn('workspace_members', $column);
            $this->line(($ok ? '[OK] ' : '[MISSING] ').'workspace_members.'.$column);
            if (! $ok) $bad++;
        }

        if (Schema::hasTable('enterprise_identity_providers')) {
            foreach (EnterpriseIdentityProvider::where('status', 'active')->get() as $provider) {
                if ($provider->type === 'saml') {
                    if (class_exists('App\\Services\\Enterprise\\SamlAssertionAdapter')) {
                        $this->line("[OK] SAML signed-assertion adapter ready: {$provider->name}");
                    } else {
                        $this->warn("[INFO] SAML {$provider->name}: metadata/configuration ready; signed assertion adapter is required before direct SAML login can be enforced.");
                    }
                } else {
                    $this->line("[OK] OIDC provider configured: {$provider->name}");
                }
            }
        }

        return $bad ? self::FAILURE : self::SUCCESS;
    }
}
