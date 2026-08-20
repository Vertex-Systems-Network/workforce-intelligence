<?php
namespace App\Http\Middleware;
use App\Models\PartnerApiKey;use Closure;use Illuminate\Http\Request;use Symfony\Component\HttpFoundation\Response;
/** Provides require partner scope behavior within the WorkIntel application. */ class RequirePartnerScope{/** Executes the command, job, or request handler. */ public function handle(Request $request,Closure $next,string ...$scopes):Response{$key=$request->attributes->get('partnerApiKey');abort_unless($key instanceof PartnerApiKey,401);foreach($scopes as $scope)if($key->allows($scope))return $next($request);abort(403,'The partner API key does not have the required scope.');}}
