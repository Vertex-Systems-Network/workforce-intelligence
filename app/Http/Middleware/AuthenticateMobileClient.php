<?php

namespace App\Http\Middleware;

use App\Models\MobileAccessToken;
use App\Models\WorkspaceMember;
use App\Services\Enterprise\EnterpriseSecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/** Provides authenticate mobile client behavior within the WorkIntel application. */ class AuthenticateMobileClient
{
    /** Executes the command, job, or request handler. */ public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->bearerToken();
        abort_unless($raw && str_starts_with($raw, 'wim_'), 401, 'Mobile authentication required.');

        $token = MobileAccessToken::query()->where('token_hash', hash('sha256', $raw))->first();
        abort_unless(
            $token && ! $token->revoked_at && (! $token->expires_at || $token->expires_at->isFuture()),
            401,
            'Mobile token is invalid, expired or revoked.'
        );

        $member = WorkspaceMember::query()
            ->with(['workspace', 'user.mfaMethod', 'roles.permissions'])
            ->whereKey($token->member_id)
            ->where('workspace_id', $token->workspace_id)
            ->where('status', 'active')
            ->first();
        abort_unless($member, 401, 'Mobile membership is inactive.');

        if (Schema::hasTable('workspace_access_policies')) {
            app(EnterpriseSecurityService::class)->assertAttributePolicy($request, $member, 'field', '*');
        }

        $token->update(['last_used_at' => now(), 'last_used_ip' => $request->ip()]);
        $request->attributes->set('mobileToken', $token);
        $request->attributes->set('workspace', $member->workspace);
        $request->attributes->set('workspaceMember', $member);
        $request->setUserResolver(fn () => $member->user);

        return $next($request);
    }
}
