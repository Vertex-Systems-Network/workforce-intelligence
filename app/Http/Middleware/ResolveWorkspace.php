<?php

namespace App\Http\Middleware;

use App\Models\WorkspaceMember;
use App\Services\Enterprise\EnterpriseSecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/** Provides resolve workspace behavior within the WorkIntel application. */ class ResolveWorkspace
{
    /** Executes the command, job, or request handler. */ public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        abort_if((bool) $user->force_password_change, 428, 'Password change is required before workspace access.');

        $requestedWorkspaceId = $request->header('X-Workspace-Id');

        $membershipQuery = WorkspaceMember::query()
            ->with(['workspace', 'roles.permissions'])
            ->where('user_id', $user->id)
            ->where('status', 'active');

        if ($requestedWorkspaceId) {
            $membershipQuery->where('workspace_id', $requestedWorkspaceId);
        }

        $membership = $membershipQuery->first();
        abort_unless($membership, 403, 'You do not have access to this workspace.');
        if (Schema::hasColumn('workspace_members', 'external_expires_at')) {
            abort_if($membership->externalExpired(), 403, 'Your external collaboration access has expired.');
        }
        abort_unless($membership->workspace && $membership->workspace->status === 'active', 403, 'This workspace is not active.');

        $request->attributes->set('workspace', $membership->workspace);
        $request->attributes->set('workspaceMember', $membership);

        if (\Illuminate\Support\Facades\Schema::hasTable('workspace_security_policies')) {
            app(EnterpriseSecurityService::class)->assertWorkspaceAccess($request, $membership);
        }

        // P4 workspace-level module boundary. This is intentionally enforced
        // after workspace resolution and before controllers/permission checks.
        $moduleKey = \App\Support\ModuleCatalog::moduleForRequest($request);
        if ($moduleKey) app(\App\Services\Modules\WorkspaceModuleService::class)->assertEnabled($membership->workspace, $moduleKey);

        return $next($request);
    }
}
