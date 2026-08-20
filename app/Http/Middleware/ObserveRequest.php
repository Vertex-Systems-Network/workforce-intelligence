<?php

namespace App\Http\Middleware;

use App\Services\Observability\ObservabilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Records only slow or failed HTTP requests without persisting request bodies or credentials. */
class ObserveRequest
{
    /** Time the request and pass bounded metadata to the observability service after response creation. */
    public function handle(Request $request,Closure $next): Response
    {
        $started=hrtime(true);$response=$next($request);$duration=(hrtime(true)-$started)/1_000_000;
        try{app(ObservabilityService::class)->recordRequest($request,$response,$duration);}catch(\Throwable){}
        return $response;
    }
}
