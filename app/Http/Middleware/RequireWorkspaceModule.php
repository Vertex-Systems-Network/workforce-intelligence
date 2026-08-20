<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Services\Modules\WorkspaceModuleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Provides require workspace module behavior within the WorkIntel application. */ class RequireWorkspaceModule
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly WorkspaceModuleService $modules) {}

    /** Executes the command, job, or request handler. */ public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $workspace = $this->resolveWorkspace($request);
        abort_unless($workspace, 400, 'Workspace context is required for module access.');
        $this->modules->assertEnabled($workspace, $moduleKey);
        return $next($request);
    }

    /** Returns resolve workspace data required by the current workflow. */ private function resolveWorkspace(Request $request): ?Workspace
    {
        $workspace = $request->attributes->get('workspace');
        if ($workspace instanceof Workspace) return $workspace;

        $device = $request->attributes->get('device');
        if ($device?->workspace_id) return Workspace::find($device->workspace_id);

        $connection = $request->attributes->get('browserConnection');
        if ($connection?->workspace_id) return Workspace::find($connection->workspace_id);

        $account = $request->attributes->get('clientPortalAccount');
        if ($account?->workspace_id) return Workspace::find($account->workspace_id);

        $token = $request->attributes->get('mobileToken');
        if ($token?->workspace_id) return Workspace::find($token->workspace_id);

        return null;
    }
}
