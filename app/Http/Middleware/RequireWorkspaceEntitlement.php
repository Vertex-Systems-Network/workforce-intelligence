<?php

namespace App\Http\Middleware;

use App\Services\Billing\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Provides require workspace entitlement behavior within the WorkIntel application. */ class RequireWorkspaceEntitlement
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly EntitlementService $entitlements) {}

    /** Executes the command, job, or request handler. */ public function handle(Request $request, Closure $next, string $feature): Response
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($workspace, 400, 'Workspace context is required.');
        if (! $this->entitlements->allows($workspace, $feature)) {
            return response()->json([
                'message' => 'This feature is not included in the current workspace plan.',
                'code' => 'PLAN_FEATURE_REQUIRED',
                'feature' => $feature,
            ], 402);
        }
        return $next($request);
    }
}
