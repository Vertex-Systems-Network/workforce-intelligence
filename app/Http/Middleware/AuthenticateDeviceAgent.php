<?php

namespace App\Http\Middleware;

use App\Models\DeviceAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Provides authenticate device agent behavior within the WorkIntel application. */ class AuthenticateDeviceAgent
{
    /** Executes the command, job, or request handler. */ public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        abort_unless($plainToken && str_starts_with($plainToken, 'wia_'), 401, 'A valid device token is required.');

        $token = DeviceAccessToken::query()
            ->with(['device.member'])
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('revoked_at')
            ->first();

        abort_unless($token, 401, 'The device token is invalid or revoked.');
        abort_if($token->expires_at && $token->expires_at->isPast(), 401, 'The device token has expired.');
        abort_unless($token->device && $token->device->status === 'active' && ! $token->device->revoked_at, 401, 'This device has been revoked.');

        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->save();
        }
        $request->attributes->set('deviceAccessToken', $token);
        $request->attributes->set('device', $token->device);

        return $next($request);
    }
}
