@echo off
setlocal EnableExtensions
cd /d %~dp0

echo ============================================================
echo WorkIntel - Repair Laravel Runtime Directories
echo ============================================================

where php >nul 2>nul || (echo ERROR: PHP not found in PATH.& exit /b 1)
php tools\prepare-runtime.php || exit /b 1
if exist artisan php artisan optimize:clear || exit /b 1

echo.
echo Runtime directories repaired successfully.
echo storage\framework\sessions, views, cache\data, storage\logs and bootstrap\cache are ready.
exit /b 0
