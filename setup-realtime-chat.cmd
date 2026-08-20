@echo off
setlocal
cd /d %~dp0

echo [P10] Installing Laravel Reverb for push realtime...
where composer >nul 2>nul || (echo ERROR: Composer not found.& exit /b 1)
call composer require laravel/reverb:^1.10 --with-all-dependencies || exit /b 1
php artisan reverb:install --no-interaction || exit /b 1
call npm install || exit /b 1

echo.
echo Reverb package installed.
echo Configure REVERB_APP_ID, REVERB_APP_KEY, REVERB_APP_SECRET, REVERB_HOST, REVERB_PORT and VITE_REVERB_APP_KEY in .env.
echo Then run: npm run build
echo Start server with: php artisan reverb:start
exit /b 0
