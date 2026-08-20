@echo off
setlocal
cd /d %~dp0

echo WorkIntel development setup delegates to the strict zero-install verifier.
echo It is destructive and requires typing RESET before any migrate:fresh command.
call verify-clean-install.cmd
exit /b %errorlevel%
