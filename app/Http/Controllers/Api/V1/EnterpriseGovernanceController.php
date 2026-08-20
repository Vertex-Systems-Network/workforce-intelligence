<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\DataGovernancePolicy;
use App\Models\EnterpriseIdentityProvider;
use App\Models\LegalEntity;
use App\Models\MobileAccessToken;
use App\Models\CostCenter;
use App\Models\Project;
use App\Models\Role;
use App\Models\ScimAccessToken;
use App\Models\UserMfaMethod;
use App\Models\WorkspaceAccessPolicy;
use App\Models\WorkspaceAccessSession;
use App\Models\WorkspaceIpRule;
use App\Models\WorkspaceMember;
use App\Services\Enterprise\EnterpriseSecurityService;
use App\Services\Enterprise\OidcService;
use App\Services\Enterprise\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Provides enterprise governance controller behavior within the WorkIntel application. */ class EnterpriseGovernanceController extends Controller
{
    /** Handles the overview operation for the current WorkIntel workflow. */ public function overview(Request $request, EnterpriseSecurityService $security, TotpService $totp): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        abort_unless(
            $member->hasPermission('enterprise.identity.manage')
                || $member->hasPermission('enterprise.security.manage')
                || $member->hasPermission('enterprise.governance.manage'),
            403
        );

        $providers = EnterpriseIdentityProvider::where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($provider) => [
                'id' => $provider->id,
                'uuid' => $provider->uuid,
                'name' => $provider->name,
                'type' => $provider->type,
                'status' => $provider->status,
                'domains' => $provider->domains,
                'enforce_login' => $provider->enforce_login,
                'jit_provisioning' => $provider->jit_provisioning,
                'default_role_slug' => $provider->default_role_slug,
                'runtime_ready' => $provider->type === 'oidc' || class_exists('App\\Services\\Enterprise\\SamlAssertionAdapter'),
                'created_at' => $provider->created_at,
            ]);

        $mobileTokens = Schema::hasTable('mobile_access_tokens')
            ? MobileAccessToken::where('workspace_id', $workspace->id)
                ->orderByDesc('last_used_at')
                ->limit(200)
                ->get(['id', 'uuid', 'member_id', 'token_prefix', 'device_uuid', 'platform', 'device_name', 'app_version', 'last_used_at', 'last_used_ip', 'expires_at', 'revoked_at', 'created_at'])
            : collect();

        return response()->json([
            'providers' => $providers,
            'security_policy' => $security->policy($workspace),
            'ip_rules' => WorkspaceIpRule::where('workspace_id', $workspace->id)->orderBy('priority')->get(),
            'access_policies' => WorkspaceAccessPolicy::where('workspace_id', $workspace->id)->orderBy('priority')->orderBy('id')->get(),
            'scim_tokens' => ScimAccessToken::where('workspace_id', $workspace->id)->orderByDesc('id')->get([
                'id', 'uuid', 'name', 'token_prefix', 'scopes', 'last_used_at', 'expires_at', 'revoked_at', 'created_at',
            ]),
            'sessions' => WorkspaceAccessSession::where('workspace_id', $workspace->id)
                ->with(['user:id,first_name,last_name,email'])
                ->orderByDesc('last_seen_at')
                ->limit(200)
                ->get(),
            'mobile_sessions' => $mobileTokens,
            'legal_entities' => LegalEntity::where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'business_units' => BusinessUnit::where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'governance' => DataGovernancePolicy::where('workspace_id', $workspace->id)->orderBy('dataset')->get(),
            'organization_members' => WorkspaceMember::where('workspace_id', $workspace->id)->with('user:id,first_name,last_name,email')->orderBy('id')->get(['id','user_id','employee_code','legal_entity_id','business_unit_id']),
            'organization_projects' => Project::where('workspace_id', $workspace->id)->orderBy('name')->get(['id','name','code','legal_entity_id','business_unit_id']),
            'organization_cost_centers' => Schema::hasTable('cost_centers') ? CostCenter::where('workspace_id', $workspace->id)->orderBy('name')->get(['id','name','code','legal_entity_id','business_unit_id']) : collect(),
            'mfa' => [
                'enabled' => Schema::hasTable('user_mfa_methods') ? $totp->enabled($request->user()) : false,
            ],
        ]);
    }

    /** Handles the store provider operation for the current WorkIntel workflow. */ public function storeProvider(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'name' => 'required|string|max:140',
            'type' => ['required', Rule::in(['oidc', 'saml'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'domains' => 'nullable|array',
            'domains.*' => 'string|max:255',
            'config' => 'required|array',
            'enforce_login' => 'boolean',
            'jit_provisioning' => 'boolean',
            'default_role_slug' => 'nullable|string|max:80',
        ]);

        $this->validateProviderConfig($data['type'], $data['config']);
        $row = EnterpriseIdentityProvider::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'status' => $data['status'] ?? 'inactive',
            'domains' => $data['domains'] ?? [],
            'config_encrypted' => Crypt::encryptString(json_encode($data['config'], JSON_THROW_ON_ERROR)),
            'enforce_login' => $data['enforce_login'] ?? false,
            'jit_provisioning' => $data['jit_provisioning'] ?? false,
            'default_role_slug' => $data['default_role_slug'] ?? 'employee',
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->providerPayload($row)], 201);
    }

    /** Updates update provider data for the requested resource. */ public function updateProvider(Request $request, EnterpriseIdentityProvider $provider): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $provider->workspace_id === (int) $workspace->id, 404);

        $data = $request->validate([
            'name' => 'sometimes|string|max:140',
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'domains' => 'nullable|array',
            'domains.*' => 'string|max:255',
            'enforce_login' => 'boolean',
            'jit_provisioning' => 'boolean',
            'default_role_slug' => 'nullable|string|max:80',
            'config' => 'nullable|array',
        ]);

        if (isset($data['config'])) {
            $this->validateProviderConfig($provider->type, $data['config']);
            $data['config_encrypted'] = Crypt::encryptString(json_encode($data['config'], JSON_THROW_ON_ERROR));
            unset($data['config']);
        }

        $provider->update($data);
        return response()->json(['data' => $this->providerPayload($provider->fresh())]);
    }

    /** Handles the test provider operation for the current WorkIntel workflow. */ public function testProvider(Request $request, EnterpriseIdentityProvider $provider, OidcService $service): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $provider->workspace_id === (int) $workspace->id, 404);
        return response()->json(['data' => $service->test($provider)]);
    }

    /** Updates update security policy data for the requested resource. */ public function updateSecurityPolicy(Request $request, EnterpriseSecurityService $security): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'require_mfa' => 'boolean',
            'mfa_role_slugs' => 'nullable|array',
            'mfa_role_slugs.*' => 'string|max:80',
            'session_ttl_minutes' => 'integer|min:15|max:43200',
            'max_active_sessions' => 'integer|min:1|max:100',
            'allow_password_login' => 'boolean',
            'require_sso' => 'boolean',
            'password_min_length' => 'integer|min:8|max:128',
            'block_compromised_devices' => 'boolean',
        ]);

        if (($data['require_mfa'] ?? false)) $security->assertCanEnableMfa($workspace, $data['mfa_role_slugs'] ?? []);
        if (($data['require_sso'] ?? false)) $security->assertCanRequireSso($workspace);

        $policy = $security->policy($workspace);
        $policy->update($data);
        return response()->json(['data' => $policy->fresh()]);
    }

    /** Handles the store ip rule operation for the current WorkIntel workflow. */ public function storeIpRule(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'cidr' => 'required|string|max:64',
            'action' => ['required', Rule::in(['allow', 'deny'])],
            'priority' => 'integer|min:1|max:1000',
            'active' => 'boolean',
        ]);
        abort_unless($this->validCidr($data['cidr']), 422, 'CIDR is invalid.');

        return response()->json(['data' => WorkspaceIpRule::create(['workspace_id' => $workspace->id, ...$data])], 201);
    }

    /** Removes delete ip rule data from the requested resource. */ public function deleteIpRule(Request $request, WorkspaceIpRule $rule): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $rule->workspace_id === (int) $workspace->id, 404);
        $rule->delete();
        return response()->json(['message' => 'IP rule removed.']);
    }

    /** Handles the store access policy operation for the current WorkIntel workflow. */ public function storeAccessPolicy(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'name' => 'required|string|max:140',
            'resource' => ['required', Rule::in(['*', 'workspace', 'people', 'projects', 'hris', 'performance', 'payroll', 'finance', 'field', 'approvals', 'scheduling', 'enterprise', 'reports', 'automations', 'intelligence', 'platform'])],
            'action' => 'sometimes|string|max:60',
            'effect' => ['required', Rule::in(['allow', 'deny'])],
            'priority' => 'integer|min:1|max:1000',
            'active' => 'boolean',
            'conditions' => 'nullable|array',
            'conditions.role_slugs' => 'nullable|array',
            'conditions.role_slugs.*' => 'string|max:80',
            'conditions.legal_entity_ids' => 'nullable|array',
            'conditions.legal_entity_ids.*' => 'integer',
            'conditions.business_unit_ids' => 'nullable|array',
            'conditions.business_unit_ids.*' => 'integer',
            'conditions.employment_stages' => 'nullable|array',
            'conditions.employment_stages.*' => 'string|max:40',
            'conditions.ip_cidrs' => 'nullable|array',
            'conditions.ip_cidrs.*' => 'string|max:64',
        ]);

        $validRoleSlugs = Role::where('workspace_id', $workspace->id)->where('status','active')->pluck('slug')->all();
        foreach (($data['conditions']['role_slugs'] ?? []) as $slug) {
            abort_unless(in_array($slug, $validRoleSlugs, true), 422, "Unknown workspace role slug: {$slug}");
        }
        foreach (($data['conditions']['employment_stages'] ?? []) as $stage) {
            abort_unless(in_array($stage, ['preboarding','onboarding','probation','active','notice','terminated','alumni'], true), 422, "Unknown employment stage: {$stage}");
        }

        foreach (($data['conditions']['legal_entity_ids'] ?? []) as $id) {
            LegalEntity::where('workspace_id', $workspace->id)->findOrFail($id);
        }
        foreach (($data['conditions']['business_unit_ids'] ?? []) as $id) {
            BusinessUnit::where('workspace_id', $workspace->id)->findOrFail($id);
        }
        foreach (($data['conditions']['ip_cidrs'] ?? []) as $cidr) {
            abort_unless($this->validCidr($cidr), 422, "Invalid ABAC CIDR: {$cidr}");
        }

        $row = WorkspaceAccessPolicy::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'resource' => $data['resource'],
            'action' => $data['action'] ?? '*',
            'effect' => $data['effect'],
            'priority' => $data['priority'] ?? 100,
            'conditions' => $data['conditions'] ?? [],
            'active' => $data['active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $row], 201);
    }

    /** Removes delete access policy data from the requested resource. */ public function deleteAccessPolicy(Request $request, WorkspaceAccessPolicy $policy): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $policy->workspace_id === (int) $workspace->id, 404);
        $policy->delete();
        return response()->json(['message' => 'Attribute access policy removed.']);
    }

    /** Handles the revoke session operation for the current WorkIntel workflow. */ public function revokeSession(Request $request, WorkspaceAccessSession $session): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $session->workspace_id === (int) $workspace->id, 404);
        $session->update([
            'revoked_at' => now(),
            'revoke_reason' => $request->input('reason', 'Revoked by administrator.'),
        ]);
        return response()->json(['data' => $session->fresh()]);
    }

    /** Handles the revoke mobile session operation for the current WorkIntel workflow. */ public function revokeMobileSession(Request $request, MobileAccessToken $token): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $token->workspace_id === (int) $workspace->id, 404);
        $token->update(['revoked_at' => now()]);
        return response()->json(['data' => $token->fresh()]);
    }

    /** Creates create scim token data for the requested workflow. */ public function createScimToken(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'scopes' => 'nullable|array',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $raw = 'wiscim_'.Str::random(64);
        $row = ScimAccessToken::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'token_hash' => hash('sha256', $raw),
            'token_prefix' => substr($raw, 0, 16),
            'scopes' => $data['scopes'] ?? ['users.read', 'users.write', 'groups.read', 'groups.write'],
            'expires_at' => $data['expires_at'] ?? now()->addYear(),
            'created_by' => $request->user()->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $row->only(['id', 'uuid', 'name', 'token_prefix', 'scopes', 'expires_at']),
            'token' => $raw,
            'warning' => 'Copy this SCIM token now; it will not be shown again.',
        ], 201);
    }

    /** Handles the revoke scim token operation for the current WorkIntel workflow. */ public function revokeScimToken(Request $request, ScimAccessToken $token): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $token->workspace_id === (int) $workspace->id, 404);
        $token->update(['revoked_at' => now()]);
        return response()->json(['data' => $token->fresh()]);
    }

    /** Handles the store legal entity operation for the current WorkIntel workflow. */ public function storeLegalEntity(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'code' => 'required|string|max:40',
            'name' => 'required|string|max:180',
            'country_code' => 'nullable|string|size:2',
            'registration_number' => 'nullable|string|max:120',
            'tax_identifier' => 'nullable|string|max:120',
            'currency' => 'required|string|size:3',
            'timezone' => 'required|string|max:80',
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $row = LegalEntity::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            ...$data,
            'country_code' => isset($data['country_code']) ? strtoupper($data['country_code']) : null,
            'currency' => strtoupper($data['currency']),
            'status' => $data['status'] ?? 'active',
        ]);
        return response()->json(['data' => $row], 201);
    }

    /** Handles the store business unit operation for the current WorkIntel workflow. */ public function storeBusinessUnit(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'legal_entity_id' => 'nullable|integer',
            'parent_id' => 'nullable|integer',
            'code' => 'required|string|max:40',
            'name' => 'required|string|max:160',
            'leader_member_id' => 'nullable|integer',
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        if ($data['legal_entity_id'] ?? null) LegalEntity::where('workspace_id', $workspace->id)->findOrFail($data['legal_entity_id']);
        if ($data['parent_id'] ?? null) BusinessUnit::where('workspace_id', $workspace->id)->findOrFail($data['parent_id']);
        if ($data['leader_member_id'] ?? null) WorkspaceMember::where('workspace_id', $workspace->id)->findOrFail($data['leader_member_id']);

        $row = BusinessUnit::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            ...$data,
            'status' => $data['status'] ?? 'active',
        ]);
        return response()->json(['data' => $row], 201);
    }

    /** Handles the save governance policy operation for the current WorkIntel workflow. */ public function saveGovernancePolicy(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'dataset' => 'required|string|max:80',
            'retention_days' => 'nullable|integer|min:1|max:36500',
            'residency_region' => 'nullable|string|max:80',
            'storage_class' => 'required|string|max:40',
            'deletion_mode' => ['required', Rule::in(['soft_then_purge', 'hard_purge'])],
            'legal_hold' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        $row = DataGovernancePolicy::firstOrNew([
            'workspace_id' => $workspace->id,
            'dataset' => $data['dataset'],
        ]);
        if (! $row->exists) $row->uuid = (string) Str::uuid();
        $row->fill($data)->save();

        return response()->json(['data' => $row->fresh()]);
    }

    /** Handles the assign member organization operation for the current WorkIntel workflow. */ public function assignMemberOrganization(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $member->workspace_id === (int) $workspace->id, 404);
        [$entityId, $unitId] = $this->validatedOrganizationAssignment($workspace->id, $request);
        $member->update(['legal_entity_id' => $entityId, 'business_unit_id' => $unitId]);
        return response()->json(['data' => $member->fresh(['legalEntity','businessUnit','user'])]);
    }

    /** Handles the assign project organization operation for the current WorkIntel workflow. */ public function assignProjectOrganization(Request $request, Project $project): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $project->workspace_id === (int) $workspace->id, 404);
        [$entityId, $unitId] = $this->validatedOrganizationAssignment($workspace->id, $request);
        $project->update(['legal_entity_id' => $entityId, 'business_unit_id' => $unitId]);
        return response()->json(['data' => $project->fresh(['legalEntity','businessUnit'])]);
    }

    /** Handles the assign cost center organization operation for the current WorkIntel workflow. */ public function assignCostCenterOrganization(Request $request, CostCenter $costCenter): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $costCenter->workspace_id === (int) $workspace->id, 404);
        [$entityId, $unitId] = $this->validatedOrganizationAssignment($workspace->id, $request);
        $costCenter->update(['legal_entity_id' => $entityId, 'business_unit_id' => $unitId]);
        return response()->json(['data' => $costCenter->fresh(['legalEntity','businessUnit'])]);
    }

    /** Validates validated organization assignment input before it is processed. */ private function validatedOrganizationAssignment(int $workspaceId, Request $request): array
    {
        $data = $request->validate(['legal_entity_id' => 'nullable|integer', 'business_unit_id' => 'nullable|integer']);
        $entity = isset($data['legal_entity_id']) ? LegalEntity::where('workspace_id', $workspaceId)->findOrFail($data['legal_entity_id']) : null;
        $unit = isset($data['business_unit_id']) ? BusinessUnit::where('workspace_id', $workspaceId)->findOrFail($data['business_unit_id']) : null;
        if ($unit?->legal_entity_id) {
            abort_if($entity && (int) $entity->id !== (int) $unit->legal_entity_id, 422, 'Business unit does not belong to the selected legal entity.');
            $entity = LegalEntity::where('workspace_id', $workspaceId)->findOrFail($unit->legal_entity_id);
        }
        return [$entity?->id, $unit?->id];
    }

    /** Handles the mfa status operation for the current WorkIntel workflow. */ public function mfaStatus(Request $request, TotpService $totp): JsonResponse
    {
        $method = $totp->method($request->user());
        return response()->json([
            'enabled' => (bool) $method?->confirmed_at,
            'recovery_codes_remaining' => count($method?->recovery_code_hashes ?? []),
        ]);
    }

    /** Handles the begin mfa operation for the current WorkIntel workflow. */ public function beginMfa(Request $request, TotpService $totp): JsonResponse
    {
        return response()->json(['data' => $totp->begin($request->user())], 201);
    }

    /** Handles the confirm mfa operation for the current WorkIntel workflow. */ public function confirmMfa(Request $request, TotpService $totp): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:32']);
        abort_unless($totp->confirm($request->user(), $data['code']), 422, 'Authenticator code is invalid.');
        if ($request->hasSession()) $request->session()->put('mfa_verified_at', now()->toIso8601String());
        return response()->json(['message' => 'MFA enabled.']);
    }

    /** Handles the disable mfa operation for the current WorkIntel workflow. */ public function disableMfa(Request $request, TotpService $totp): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:32']);
        abort_unless($totp->verify($request->user(), $data['code']), 422, 'A valid authenticator or recovery code is required.');
        UserMfaMethod::where('user_id', $request->user()->id)->delete();
        return response()->json(['message' => 'MFA disabled.']);
    }

    /** Handles the provider payload operation for the current WorkIntel workflow. */ private function providerPayload(EnterpriseIdentityProvider $provider): array
    {
        return $provider->only([
            'id', 'uuid', 'name', 'type', 'status', 'domains', 'enforce_login', 'jit_provisioning', 'default_role_slug',
        ]);
    }

    /** Validates validate provider config input before it is processed. */ private function validateProviderConfig(string $type, array $config): void
    {
        if ($type === 'oidc') {
            foreach (['client_id', 'authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $key) {
                abort_unless(filled($config[$key] ?? null), 422, "OIDC config requires {$key}.");
            }
            return;
        }

        foreach (['idp_entity_id', 'sso_url', 'x509_certificate'] as $key) {
            abort_unless(filled($config[$key] ?? null), 422, "SAML config requires {$key}.");
        }
    }

    /** Handles the valid cidr operation for the current WorkIntel workflow. */ private function validCidr(string $cidr): bool
    {
        if (! str_contains($cidr, '/')) return filter_var($cidr, FILTER_VALIDATE_IP) !== false;
        [$ip, $prefix] = explode('/', $cidr, 2);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false || ! ctype_digit($prefix)) return false;
        $max = str_contains($ip, ':') ? 128 : 32;
        return (int) $prefix >= 0 && (int) $prefix <= $max;
    }
}
