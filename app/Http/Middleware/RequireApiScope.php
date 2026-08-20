<?php
namespace App\Http\Middleware;
use App\Models\WorkspaceApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
/** Provides require api scope behavior within the WorkIntel application. */ class RequireApiScope
{
    /** Executes the command, job, or request handler. */ public function handle(Request $request,Closure $next,string ...$scopes): Response
    {
        /** @var WorkspaceApiKey|null $key */$key=$request->attributes->get('workspaceApiKey');
        abort_unless($key,401);
        foreach($scopes as $scope) if($key->allows($scope)) return $next($request);
        abort(403,'The API key does not have the required scope.');
    }
}
