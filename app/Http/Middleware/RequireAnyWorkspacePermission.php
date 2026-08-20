<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Provides require any workspace permission behavior within the WorkIntel application. */ class RequireAnyWorkspacePermission
{
    /** Executes the command, job, or request handler. */ public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $member = $request->attributes->get('workspaceMember');

        abort_unless($member, 403, 'Workspace membership is required.');
        abort_unless(
            collect($permissions)->contains(fn (string $permission) => $member->hasPermission($permission)),
            403,
            'You do not have permission to access this resource.'
        );

        return $next($request);
    }
}
