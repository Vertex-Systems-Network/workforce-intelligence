<?php

namespace App\Services\Security;

use App\Models\SecurityEvent;
use App\Models\UserMfaMethod;
use App\Models\WorkspaceApiKey;
use App\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Schema;

/** Builds a privacy-safe platform security posture summary for platform operators and diagnostics. */
class SecurityPostureService
{
    /** Return platform security posture without exposing credentials or secret values. */
    public function overview(): array
    {
        $checks = [
            $this->check('csp', 'Content Security Policy', (bool) config('workintel_security.headers.csp_enabled'), 'Enable CSP in production.'),
            $this->check('hsts', 'HTTPS Strict Transport Security', (int) config('workintel.production.hsts_seconds', 0) > 0, 'Set a production HSTS duration after HTTPS is confirmed.'),
            $this->check('secure_cookie', 'Secure session cookie', (bool) config('session.secure'), 'Production sessions must use Secure cookies.'),
            $this->check('http_only', 'HttpOnly session cookie', (bool) config('session.http_only'), 'Session cookies must remain HttpOnly.'),
            $this->check('session_encryption', 'Encrypted sessions', (bool) config('session.encrypt'), 'Enable SESSION_ENCRYPT in production.'),
            $this->check('same_site', 'SameSite session protection', in_array((string) config('session.same_site'), ['lax', 'strict'], true), 'Prefer Lax or Strict SameSite for first-party sessions.'),
            $this->check('api_expiry', 'API key expiry policy', (int) config('workintel_security.api.token_days', 365) <= 365, 'Keep API-key lifetime at 365 days or less.'),
            $this->check('upload_scan', 'Malware scanning', (string) config('workintel_security.uploads.malware_driver', 'none') !== 'none', 'Configure ClamAV before accepting untrusted production uploads.'),
        ];

        $failed = collect($checks)->where('ok', false)->count();
        $apiKeys = $this->hasTable('workspace_api_keys') ? [
            'active' => WorkspaceApiKey::query()->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'stale' => WorkspaceApiKey::query()->whereNull('revoked_at')->where('created_at', '<', now()->subDays((int) config('workintel_security.api.rotation_warning_days', 90)))->count(),
        ] : ['active' => 0, 'stale' => 0];

        return [
            'score' => max(0, 100 - ($failed * 12)),
            'checks' => $checks,
            'api_keys' => $apiKeys,
            'security_events' => $this->hasTable('security_events') ? [
                'open' => SecurityEvent::query()->whereNull('resolved_at')->count(),
                'high_open' => SecurityEvent::query()->whereNull('resolved_at')->whereIn('severity', ['high', 'critical'])->count(),
            ] : ['open' => 0, 'high_open' => 0],
            'workspace_policies' => $this->hasTable('workspace_security_policies') ? [
                'total' => WorkspaceSecurityPolicy::query()->count(),
                'mfa_required' => WorkspaceSecurityPolicy::query()->where('require_mfa', true)->count(),
                'sso_required' => WorkspaceSecurityPolicy::query()->where('require_sso', true)->count(),
            ] : ['total' => 0, 'mfa_required' => 0, 'sso_required' => 0],
            'mfa_methods' => $this->hasTable('user_mfa_methods') ? UserMfaMethod::query()->whereNotNull('confirmed_at')->count() : 0,
            'rate_limits' => config('workintel_security.rate_limits'),
            'upload_security' => [
                'driver' => (string) config('workintel_security.uploads.malware_driver', 'none'),
                'required' => (bool) config('workintel_security.uploads.malware_required', false),
                'quarantine_on_detection' => true,
            ],
        ];
    }

    /** Return false instead of crashing posture diagnostics when the packaging/runtime database driver is unavailable. */
    private function hasTable(string $table): bool
    {
        try { return Schema::hasTable($table); } catch (\Throwable) { return false; }
    }

    /** Build one normalized posture check. */
    private function check(string $key, string $label, bool $ok, string $recommendation): array
    {
        return ['key' => $key, 'label' => $label, 'ok' => $ok, 'recommendation' => $ok ? null : $recommendation];
    }
}
