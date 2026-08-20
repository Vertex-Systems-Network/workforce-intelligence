<?php

namespace App\Services\Enterprise;

use App\Models\EnterpriseIdentityProvider;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAccessPolicy;
use App\Models\WorkspaceAccessSession;
use App\Models\WorkspaceIpRule;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSecurityPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Provides enterprise security service behavior within the WorkIntel application. */ class EnterpriseSecurityService
{
    /** Handles the policy operation for the current WorkIntel workflow. */ public function policy(Workspace $workspace): WorkspaceSecurityPolicy
    {
        return WorkspaceSecurityPolicy::firstOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'require_mfa' => false,
                'mfa_role_slugs' => ['owner', 'admin'],
                'session_ttl_minutes' => 720,
                'max_active_sessions' => 10,
                'allow_password_login' => true,
                'require_sso' => false,
                'password_min_length' => 12,
                'block_compromised_devices' => false,
            ]
        );
    }

    /** Handles the requires mfa operation for the current WorkIntel workflow. */ public function requiresMfa(User $user): bool
    {
        $memberships = $user->memberships()
            ->where('status', 'active')
            ->with(['workspace', 'roles'])
            ->get();

        foreach ($memberships as $membership) {
            $policy = $this->policy($membership->workspace);
            if (! $policy->require_mfa) continue;

            $roleSlugs = $membership->roles->pluck('slug')->all();
            $targets = $policy->mfa_role_slugs ?? [];
            if (! $targets || array_intersect($roleSlugs, $targets)) return true;
        }

        return false;
    }

    /** Handles the assert workspace access operation for the current WorkIntel workflow. */ public function assertWorkspaceAccess(Request $request, WorkspaceMember $member): void
    {
        $policy = $this->policy($member->workspace);
        $this->assertIp($member->workspace_id, $request->ip());

        $isOwner = $member->roles->contains('slug', 'owner');
        if ($policy->require_sso && ! $isOwner) {
            $ssoWorkspaceId = (int) ($request->hasSession()
                ? $request->session()->get('enterprise_sso_workspace_id', 0)
                : 0);
            abort_unless($ssoWorkspaceId === (int) $member->workspace_id, 403, 'This workspace requires enterprise SSO.');
        }

        if ($policy->require_mfa) {
            $targets = $policy->mfa_role_slugs ?? [];
            $roleSlugs = $member->roles->pluck('slug')->all();
            if (! $targets || array_intersect($roleSlugs, $targets)) {
                abort_unless((bool) $member->user->mfaMethod?->confirmed_at, 403, 'MFA enrollment is required.');
                if ($request->hasSession()) {
                    abort_unless((bool) $request->session()->get('mfa_verified_at'), 403, 'MFA verification is required for this session.');
                }
            }
        }

        $this->assertAttributePolicy($request, $member, 'workspace', '*');
        $this->recordSession($request, $member, $policy);
    }

    /**
     * Evaluate ABAC policies. Deny rules always win. If matching active allow
     * rules exist for a resource, at least one allow rule must match.
     */
    /** Handles the assert attribute policy operation for the current WorkIntel workflow. */ public function assertAttributePolicy(Request $request, WorkspaceMember $member, string $resource, string $action = '*'): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('workspace_access_policies')) return;

        $rules = WorkspaceAccessPolicy::query()
            ->where('workspace_id', $member->workspace_id)
            ->where('active', true)
            ->where(function ($query) use ($resource) {
                $query->where('resource', $resource)->orWhere('resource', '*');
            })
            ->where(function ($query) use ($action) {
                $query->where('action', $action)->orWhere('action', '*');
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) return;

        $allowRulesExist = $rules->contains('effect', 'allow');
        $matchedAllow = false;

        foreach ($rules as $rule) {
            if (! $this->matchesConditions($request, $member, $rule->conditions ?? [])) continue;
            if ($rule->effect === 'deny') {
                abort(403, 'Access is denied by an enterprise attribute policy.');
            }
            if ($rule->effect === 'allow') $matchedAllow = true;
        }

        if ($allowRulesExist && ! $matchedAllow) {
            abort(403, 'Your workspace attributes do not satisfy the required access policy.');
        }
    }

    /** Handles the assert can enable mfa operation for the current WorkIntel workflow. */ public function assertCanEnableMfa(Workspace $workspace, array $roles): void
    {
        $members = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->when($roles, fn ($query) => $query->whereHas('roles', fn ($rolesQuery) => $rolesQuery->whereIn('slug', $roles)))
            ->with('user.mfaMethod')
            ->get();

        $missing = $members->filter(fn ($member) => ! $member->user->mfaMethod?->confirmed_at);
        abort_if(
            $missing->isNotEmpty(),
            422,
            'Cannot require MFA yet: '.$missing->count().' targeted active member(s) have not enrolled MFA.'
        );
    }

    /** Handles the assert can require sso operation for the current WorkIntel workflow. */ public function assertCanRequireSso(Workspace $workspace): void
    {
        $providers = EnterpriseIdentityProvider::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->get();

        $hasUsableProvider = $providers->contains(function (EnterpriseIdentityProvider $provider) {
            if ($provider->type === 'oidc') return true;
            if ($provider->type !== 'saml') return false;

            // SAML assertion processing is enabled only when a standards-compliant
            // adapter is installed. Configuration alone must never satisfy a
            // require-SSO lockout check.
            return class_exists('App\\Services\\Enterprise\\SamlAssertionAdapter');
        });

        abort_unless(
            $hasUsableProvider,
            422,
            'Activate a usable OIDC provider (or install the signed-assertion SAML adapter) before requiring SSO.'
        );
    }

    /** Handles the matches conditions operation for the current WorkIntel workflow. */ private function matchesConditions(Request $request, WorkspaceMember $member, array $conditions): bool
    {
        if (($conditions['role_slugs'] ?? null)) {
            $memberRoles = $member->roles->pluck('slug')->all();
            if (! array_intersect($memberRoles, (array) $conditions['role_slugs'])) return false;
        }

        if (($conditions['legal_entity_ids'] ?? null)
            && ! in_array((int) $member->legal_entity_id, array_map('intval', (array) $conditions['legal_entity_ids']), true)) {
            return false;
        }

        if (($conditions['business_unit_ids'] ?? null)
            && ! in_array((int) $member->business_unit_id, array_map('intval', (array) $conditions['business_unit_ids']), true)) {
            return false;
        }

        if (($conditions['employment_stages'] ?? null)
            && ! in_array((string) $member->employment_stage, (array) $conditions['employment_stages'], true)) {
            return false;
        }

        if (($conditions['ip_cidrs'] ?? null)) {
            $ip = $request->ip();
            if (! $ip) return false;
            $matched = collect((array) $conditions['ip_cidrs'])->contains(fn ($cidr) => $this->cidrMatch($ip, (string) $cidr));
            if (! $matched) return false;
        }

        return true;
    }

    /** Handles the assert ip operation for the current WorkIntel workflow. */ private function assertIp(int $workspaceId, ?string $ip): void
    {
        if (! $ip) return;

        $rules = WorkspaceIpRule::query()
            ->where('workspace_id', $workspaceId)
            ->where('active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) return;

        $matched = null;
        foreach ($rules as $rule) {
            if ($this->cidrMatch($ip, $rule->cidr)) {
                $matched = $rule;
                break;
            }
        }

        if ($matched) {
            abort_if($matched->action === 'deny', 403, 'Your IP address is blocked by workspace policy.');
            return;
        }

        if ($rules->contains('action', 'allow')) {
            abort(403, 'Your IP address is outside the workspace allow list.');
        }
    }

    /** Handles the record session operation for the current WorkIntel workflow. */ private function recordSession(Request $request, WorkspaceMember $member, WorkspaceSecurityPolicy $policy): void
    {
        if (! $request->hasSession()) return;

        $hash = hash('sha256', $request->session()->getId());
        $row = WorkspaceAccessSession::firstOrCreate(
            ['workspace_id' => $member->workspace_id, 'session_hash' => $hash],
            [
                'uuid' => (string) Str::uuid(),
                'user_id' => $member->user_id,
                'member_id' => $member->id,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'last_seen_at' => now(),
                'expires_at' => now()->addMinutes($policy->session_ttl_minutes),
                'created_at' => now(),
            ]
        );

        abort_if($row->revoked_at, 401, 'This session has been revoked.');
        abort_if($row->expires_at && $row->expires_at->isPast(), 401, 'This session has expired.');

        $row->update(['last_seen_at' => now(), 'ip_address' => $request->ip()]);

        $active = WorkspaceAccessSession::query()
            ->where('workspace_id', $member->workspace_id)
            ->where('user_id', $member->user_id)
            ->whereNull('revoked_at')
            ->orderByDesc('last_seen_at')
            ->get();

        foreach ($active->slice(max(1, $policy->max_active_sessions)) as $old) {
            $old->update(['revoked_at' => now(), 'revoke_reason' => 'Maximum active sessions exceeded.']);
        }
    }

    /** Handles the cidr match operation for the current WorkIntel workflow. */ private function cidrMatch(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) return $ip === $cidr;

        [$network, $prefix] = explode('/', $cidr, 2);
        $ipBinary = @inet_pton($ip);
        $networkBinary = @inet_pton($network);
        if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary)) return false;

        $bits = (int) $prefix;
        $maxBits = strlen($ipBinary) * 8;
        if ($bits < 0 || $bits > $maxBits) return false;

        $wholeBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;
        if (substr($ipBinary, 0, $wholeBytes) !== substr($networkBinary, 0, $wholeBytes)) return false;
        if ($remainingBits === 0) return true;

        $mask = (0xff << (8 - $remainingBits)) & 0xff;
        return (ord($ipBinary[$wholeBytes]) & $mask) === (ord($networkBinary[$wholeBytes]) & $mask);
    }
}
