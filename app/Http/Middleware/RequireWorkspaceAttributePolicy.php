<?php
namespace App\Http\Middleware;
use App\Services\Enterprise\EnterpriseSecurityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
/** Provides require workspace attribute policy behavior within the WorkIntel application. */ class RequireWorkspaceAttributePolicy
{
    /** Executes the command, job, or request handler. */ public function handle(Request $request, Closure $next, string $resource='workspace', string $action='*'): Response
    {
        $member=$request->attributes->get('workspaceMember');
        abort_unless($member,403,'Workspace membership is required.');
        app(EnterpriseSecurityService::class)->assertAttributePolicy($request,$member,$resource,$action);
        return $next($request);
    }
}
