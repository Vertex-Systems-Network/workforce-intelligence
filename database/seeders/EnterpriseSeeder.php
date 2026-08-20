<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\DataGovernancePolicy;
use App\Models\LegalEntity;
use App\Models\Workspace;
use App\Models\WorkspaceSecurityPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Provides phase23 enterprise seeder behavior within the WorkIntel application. */ class EnterpriseSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        if (! Schema::hasTable('workspace_security_policies')) return;
        $workspace = Workspace::where('slug', 'acme-corp')->first();
        if (! $workspace) return;

        WorkspaceSecurityPolicy::firstOrCreate(
            ['workspace_id' => $workspace->id],
            ['require_mfa' => false, 'mfa_role_slugs' => ['owner', 'admin'], 'session_ttl_minutes' => 720, 'max_active_sessions' => 10, 'allow_password_login' => true, 'require_sso' => false, 'password_min_length' => 12, 'block_compromised_devices' => false]
        );

        $entity = LegalEntity::firstOrCreate(
            ['workspace_id' => $workspace->id, 'code' => 'ACME-HQ'],
            ['uuid' => (string) Str::uuid(), 'name' => 'ACME Headquarters', 'country_code' => 'AE', 'currency' => $workspace->currency ?: 'USD', 'timezone' => $workspace->timezone ?: 'UTC', 'status' => 'active']
        );
        BusinessUnit::firstOrCreate(
            ['workspace_id' => $workspace->id, 'code' => 'DELIVERY'],
            ['uuid' => (string) Str::uuid(), 'legal_entity_id' => $entity->id, 'name' => 'Delivery', 'status' => 'active']
        );

        foreach ([
            ['audit_logs', 365], ['security_events', 365], ['mobile_sync_events', 90], ['field_work_order_events', 365], ['workspace_access_sessions', 90],
        ] as [$dataset, $days]) {
            DataGovernancePolicy::firstOrCreate(
                ['workspace_id' => $workspace->id, 'dataset' => $dataset],
                ['uuid' => (string) Str::uuid(), 'retention_days' => $days, 'residency_region' => 'deployment-default', 'storage_class' => 'standard', 'deletion_mode' => 'soft_then_purge', 'legal_hold' => false]
            );
        }
        // Identity providers and SCIM tokens are intentionally not seeded:
        // fake endpoints/secrets must never make an enterprise workspace appear ready.
    }
}
