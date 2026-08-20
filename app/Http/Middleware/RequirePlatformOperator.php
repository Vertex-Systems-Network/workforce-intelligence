<?php
namespace App\Http\Middleware;
use App\Services\Commerce\PlatformOperatorService;use Closure;use Illuminate\Http\Request;use Symfony\Component\HttpFoundation\Response;
/** Provides require platform operator behavior within the WorkIntel application. */ class RequirePlatformOperator
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly PlatformOperatorService $operators){}
    /** Executes the command, job, or request handler. */ public function handle(Request $request,Closure $next):Response{$this->operators->assert($request->user());return $next($request);}
}
