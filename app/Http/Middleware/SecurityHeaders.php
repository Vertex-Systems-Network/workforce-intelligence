<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Applies browser security headers and the configurable production Content Security Policy. */
class SecurityHeaders
{
    /** Add browser hardening headers without changing the application's response body. */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', (string) config('workintel_security.headers.cross_origin_opener_policy', 'same-origin'));
        $response->headers->set('Cross-Origin-Resource-Policy', (string) config('workintel_security.headers.cross_origin_resource_policy', 'same-site'));
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        $privateResponse = $request->is('app')
            || $request->is('app/*')
            || $request->is('seller')
            || $request->is('seller/*')
            || $request->user() !== null;
        if ($privateResponse) {
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        if ((bool) config('workintel_security.headers.csp_enabled', false)) {
            $name = (bool) config('workintel_security.headers.csp_report_only', false)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';
            $response->headers->set($name, trim((string) config('workintel_security.headers.csp')));
        }

        if ($request->isSecure() && (int) config('workintel.production.hsts_seconds', 0) > 0) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.(int) config('workintel.production.hsts_seconds').'; includeSubDomains');
        }
        return $response;
    }
}
