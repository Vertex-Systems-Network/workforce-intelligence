# Production deployment

## Recommended single-node baseline

- PHP 8.3+
- MySQL 8 / compatible managed MySQL
- Node.js only on the build machine; the runtime server only needs built `public/build` assets
- `SESSION_DRIVER=database`
- `CACHE_STORE=database` (Redis is a valid higher-scale alternative)
- `QUEUE_CONNECTION=database` (Redis/SQS are valid higher-scale alternatives)
- one scheduler invocation per minute
- one or more queue workers
- HTTPS
- `APP_DEBUG=false`

## Deployment sequence

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run typecheck
npm run build
php artisan workintel:migration-doctor
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan workintel:production-check
```

Use `/health/live` for liveness and `/health/ready` for dependency readiness. Set `WORKINTEL_REQUIRE_SCHEDULER_HEARTBEAT=true` only after the scheduler is actually configured.

## Shared/multi-node deployments

Use shared database/cache/session infrastructure and S3-compatible storage for screenshots/reports when files must be visible across nodes. Ensure only one logical scheduler execution per minute (or use platform scheduler locking). Do not keep `QUEUE_CONNECTION=sync` for workload-heavy production installations.

## Security

- keep `.env` outside public exposure
- document root must be `public/`
- set `APP_DEBUG=false`
- use HTTPS before enabling HSTS
- use organization-owned signing identities for desktop binaries/packages and browser-store publication
- rotate API/webhook/provider secrets through the application rather than editing database rows
