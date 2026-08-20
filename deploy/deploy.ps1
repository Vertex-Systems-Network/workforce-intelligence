$ErrorActionPreference='Stop'
Set-Location (Resolve-Path "$PSScriptRoot\..")
php artisan down --retry=60
try {
  composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
  npm ci --no-audit --no-fund
  npm run typecheck
  npm run build
  php artisan migrate --force
  php artisan optimize
  php artisan queue:restart
  php artisan workintel:production-check
} finally {
  php artisan up
}
Write-Host 'Deployment completed. Verify /health/ready and Windows scheduler/worker services.'
