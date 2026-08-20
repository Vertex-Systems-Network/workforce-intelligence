<?php

require_once __DIR__.'/runtime.php';
workintel_prepare_runtime_directories(dirname(__DIR__));

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RequireWorkspacePermission;
use App\Http\Middleware\RequireAnyWorkspacePermission;
use App\Http\Middleware\RequireWorkspaceEntitlement;
use App\Http\Middleware\AuthenticateDeviceAgent;
use App\Http\Middleware\AuthenticateBrowserConnection;
use App\Http\Middleware\AuthenticateClientPortal;
use App\Http\Middleware\RequireApiScope;
use App\Http\Middleware\AuthenticateWorkspaceApiKey;
use App\Http\Middleware\AuditWorkspaceRequest;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\AuthenticateMobileClient;
use App\Http\Middleware\AuthenticateScimToken;
use App\Http\Middleware\RequireScimScope;
use App\Http\Middleware\RequireWorkspaceAttributePolicy;
use App\Http\Middleware\AuthenticatePartnerApiKey;
use App\Http\Middleware\RequirePartnerScope;
use App\Http\Middleware\RequireWorkspaceModule;
use App\Http\Middleware\ApplyRequestLocale;
use App\Http\Middleware\RequirePlatformOperator;
use App\Http\Middleware\ObserveRequest;
use App\Services\Observability\ObservabilityService;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->append(ObserveRequest::class);

        // Allows our first-party React SPA to authenticate API routes using
        // Laravel's normal session cookie through Sanctum.
        $middleware->statefulApi();
        $middleware->alias([
            'workspace.permission' => RequireWorkspacePermission::class,
            'workspace.permission_any' => RequireAnyWorkspacePermission::class,
            'workspace.entitlement' => RequireWorkspaceEntitlement::class,
            'agent.auth' => AuthenticateDeviceAgent::class,
            'browser.auth' => AuthenticateBrowserConnection::class,
            'client.auth' => AuthenticateClientPortal::class,
            'mobile.auth' => AuthenticateMobileClient::class,
            'scim.auth' => AuthenticateScimToken::class,
            'scim.scope' => RequireScimScope::class,
            'workspace.abac' => RequireWorkspaceAttributePolicy::class,
            'workspace.audit' => AuditWorkspaceRequest::class,
            'workspace.api' => AuthenticateWorkspaceApiKey::class,
            'api.scope' => RequireApiScope::class,
            'partner.auth' => AuthenticatePartnerApiKey::class,
            'partner.scope' => RequirePartnerScope::class,
            'workspace.module' => RequireWorkspaceModule::class,
            'locale' => ApplyRequestLocale::class,
            'platform.operator' => RequirePlatformOperator::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (\Illuminate\Http\Request $request, \Throwable $exception): bool => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $exception, \Illuminate\Http\Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) return null;
            return response()->json(['message' => 'The upload exceeds the server POST size limit. Increase post_max_size/upload_max_filesize or choose a smaller file.'], 413);
        });
        // Report unhandled exceptions into the privacy-safe observability ledger without changing Laravel's response contract.
        $exceptions->report(function (\Throwable $exception): void {
            try { app(ObservabilityService::class)->recordException($exception); } catch (\Throwable) {}
        });
    })->create();
