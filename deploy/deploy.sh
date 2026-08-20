#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
php artisan down --retry=60 || true
trap 'php artisan up >/dev/null 2>&1 || true' EXIT
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run typecheck
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart || true
php artisan workintel:production-check
php artisan up
trap - EXIT
echo "Deployment completed. Check /health/ready and supervisor/cron status."
