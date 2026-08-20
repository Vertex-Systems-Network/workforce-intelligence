<?php

namespace App\Http\Middleware;

use App\Models\BrowserAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Provides authenticate browser connection behavior within the WorkIntel application. */ class AuthenticateBrowserConnection
{
    /** Executes the command, job, or request handler. */ public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        abort_unless($plainToken && str_starts_with($plainToken, 'wib_'), 401, 'A valid browser extension token is required.');

        $token = BrowserAccessToken::query()
            ->with('connection.member')
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('revoked_at')
            ->first();

        abort_unless($token, 401, 'The browser token is invalid or revoked.');
        abort_if($token->expires_at && $token->expires_at->isPast(), 401, 'The browser token has expired.');
        abort_unless($token->connection && $token->connection->status === 'active' && ! $token->connection->revoked_at, 401, 'This browser connection has been revoked.');

        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        $request->attributes->set('browserAccessToken', $token);
        $request->attributes->set('browserConnection', $token->connection);

        return $next($request);
    }
}
