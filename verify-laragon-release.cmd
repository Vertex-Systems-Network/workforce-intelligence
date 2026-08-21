@echo off
setlocal EnableExtensions
cd /d %~dp0

where powershell >nul 2>nul || (
  echo ERROR: Windows PowerShell is required for Laragon release certification.
  exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\run-laragon-release.ps1"
exit /b %ERRORLEVEL%
