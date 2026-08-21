@echo off
setlocal EnableExtensions
cd /d %~dp0

where powershell >nul 2>nul || (
  echo ERROR: Windows PowerShell is required.
  exit /b 1
)

where gh >nul 2>nul || (
  echo ERROR: GitHub CLI ^(gh^) is required.
  echo Install GitHub CLI, authenticate with repository Administration: write, then run this command again.
  exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\apply-main-branch-protection.ps1"
exit /b %ERRORLEVEL%
