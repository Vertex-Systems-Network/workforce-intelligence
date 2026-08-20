<?php

namespace App\Providers;

use App\Contracts\ChatDlpScanner;
use App\Services\Chat\ChatDlpService;
use App\Services\Automation\ConnectorRegistry;
use App\Services\Observability\ObservabilityService;
use App\Services\Security\OutboundUrlGuard;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

/** Registers replaceable WorkIntel services and production observability listeners. */
class AppServiceProvider extends ServiceProvider
{
    /** Register application-wide service bindings and shared registries. */
    public function register(): void
    {
        $this->app->bind(ChatDlpScanner::class, ChatDlpService::class);
        $this->app->singleton(ConnectorRegistry::class, fn ($app) => new ConnectorRegistry($app->make(OutboundUrlGuard::class)));
        $this->app->singleton(ObservabilityService::class);
    }

    /** Attach low-overhead query and queue telemetry after the application container is booted. */
    public function boot(): void
    {

        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute((int) config('workintel_security.rate_limits.auth_login_per_minute', 10))->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute((int) config('workintel_security.rate_limits.auth_register_per_minute', 5))->by($request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute((int) config('workintel_security.rate_limits.password_reset_per_minute', 5))->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('public-form', fn (Request $request) => Limit::perMinute((int) config('workintel_security.rate_limits.public_form_per_minute', 20))->by($request->ip()));
        RateLimiter::for('media-upload', fn (Request $request) => Limit::perMinute((int) config('workintel_security.rate_limits.media_upload_per_minute', 60))->by(($request->user()?->id ?? 'guest').'|'.$request->ip()));

        DB::listen(function ($query): void {
            try { app(ObservabilityService::class)->recordQuery($query); } catch (\Throwable) {}
        });

        Queue::after(function (JobProcessed $event): void {
            try {
                if (Cache::add('workintel:observability:queue-heartbeat-lock', true, 30)) {
                    app(ObservabilityService::class)->heartbeat('queue', 120, ['connection'=>$event->connectionName,'queue'=>$event->job->getQueue()]);
                }
            } catch (\Throwable) {}
        });

        Queue::exceptionOccurred(function (JobExceptionOccurred $event): void {
            try { app(ObservabilityService::class)->record('queue','queue.exception','Queue job raised an exception.','error',['connection'=>$event->connectionName,'queue'=>$event->job->getQueue(),'job'=>$event->job->resolveName(),'exception_class'=>$event->exception::class],null,'queue'); } catch (\Throwable) {}
        });

        Queue::failing(function (JobFailed $event): void {
            try { app(ObservabilityService::class)->record('queue','queue.failed','Queue job exhausted retries.','critical',['connection'=>$event->connectionName,'queue'=>$event->job->getQueue(),'job'=>$event->job->resolveName(),'exception_class'=>$event->exception::class],null,'queue'); } catch (\Throwable) {}
        });
    }
}
