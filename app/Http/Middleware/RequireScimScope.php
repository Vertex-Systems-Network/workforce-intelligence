<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Provides require scim scope behavior within the WorkIntel application. */ class RequireScimScope
{
    /** Executes the command, job, or request handler. */ public function handle(Request $request, Closure $next, string $scope): Response
    {
        $token = $request->attributes->get('scimToken');
        abort_unless($token, 401, 'SCIM bearer token required.');
        $scopes = array_values(array_unique(array_map('strval', $token->scopes ?? [])));
        abort_unless(in_array('*', $scopes, true) || in_array($scope, $scopes, true), 403, 'SCIM token does not have the required scope.');
        return $next($request);
    }
}
