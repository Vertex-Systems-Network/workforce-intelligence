<?php
namespace App\Http\Middleware;
use App\Models\SecurityEvent;
use App\Models\WorkspaceApiKey;
use App\Services\Billing\EntitlementService;
use App\Services\Security\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
/** Provides authenticate workspace api key behavior within the WorkIntel application. */ class AuthenticateWorkspaceApiKey
{
    /** Executes the command, job, or request handler. */ public function handle(Request $request,Closure $next): Response
    {
        $plain=$request->bearerToken();
        abort_unless($plain&&str_starts_with($plain,'wiax_'),401,'A valid workspace API key is required.');
        $key=WorkspaceApiKey::with('workspace')->where('token_hash',hash('sha256',$plain))->whereNull('revoked_at')->first();
        abort_unless($key&&$key->workspace,401,'The API key is invalid or revoked.');
        abort_if($key->expires_at&&$key->expires_at->isPast(),401,'The API key has expired.');
        abort_unless(app(EntitlementService::class)->allows($key->workspace,'feature.api_access'),403,'The current plan does not include API access.');
        $rateKey='workintel-api:'.$key->id.':'.now()->format('YmdHi');$limit=config('workintel_security.api.rate_limit_per_minute',120);
        if(RateLimiter::tooManyAttempts($rateKey,$limit)) abort(429,'API rate limit exceeded.');
        RateLimiter::hit($rateKey,65);
        $key->forceFill(['last_used_at'=>now(),'last_used_ip'=>$request->ip()])->save();
        $request->attributes->set('workspaceApiKey',$key);$request->attributes->set('workspace',$key->workspace);
        $response=$next($request);
        try{app(AuditService::class)->recordApiKeyRequest($request,$response->getStatusCode());}catch(\Throwable $e){report($e);}
        return $response;
    }
}
