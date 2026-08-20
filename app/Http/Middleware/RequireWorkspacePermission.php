<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Provides require workspace permission behavior within the WorkIntel application. */ class RequireWorkspacePermission
{
    /** Executes the command, job, or request handler. */ public function handle(Request $request, Closure $next, string $permission): Response
    {
        $member = $request->attributes->get('workspaceMember');

        abort_unless($member, 403, 'Workspace membership is required.');
        abort_unless($member->hasPermission($permission), 403, 'You do not have permission to perform this action.');

        return $next($request);
    }
}
