<?php
namespace App\Http\Middleware;
use App\Services\Security\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
/** Provides audit workspace request behavior within the WorkIntel application. */ class AuditWorkspaceRequest
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly AuditService $audit){}
    /** Executes the command, job, or request handler. */ public function handle(Request $request,Closure $next): Response
    {
        $response=$next($request);
        if(in_array($request->method(),['POST','PUT','PATCH','DELETE'],true)){
            try{$this->audit->recordWorkspaceRequest($request,$response->getStatusCode());}catch(\Throwable $e){report($e);}
        }
        return $response;
    }
}
