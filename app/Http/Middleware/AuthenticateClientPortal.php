<?php

namespace App\Http\Middleware;

use App\Models\ClientPortalToken;
use App\Services\Billing\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Provides authenticate client portal behavior within the WorkIntel application. */ class AuthenticateClientPortal
{
    /** Executes the command, job, or request handler. */ public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        abort_unless($plainToken && str_starts_with($plainToken, 'wicp_'), 401, 'A valid client portal token is required.');

        $token = ClientPortalToken::query()
            ->with(['account.client', 'account.workspace'])
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('revoked_at')
            ->first();

        abort_unless($token, 401, 'The client portal token is invalid or revoked.');
        abort_if($token->expires_at && $token->expires_at->isPast(), 401, 'The client portal session has expired.');
        abort_unless($token->account && $token->account->status === 'active', 403, 'This client portal account is not active.');

        app(EntitlementService::class)->assertFeature($token->account->workspace, 'feature.client_portal');
        $token->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('clientPortalToken', $token);
        $request->attributes->set('clientPortalAccount', $token->account);
        $request->attributes->set('client', $token->account->client);
        $request->attributes->set('workspace', $token->account->workspace);

        return $next($request);
    }
}
