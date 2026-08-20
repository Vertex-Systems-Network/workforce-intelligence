@echo off
setlocal
cd /d "%~dp0"
echo ============================================================
echo WorkIntel Block N Final Sync Verification
echo ============================================================
php tools\block-n-final-sync-check.php
if errorlevel 1 (
  echo.
  echo SYNC CHECK FAILED - do not run the full PHPUnit suite yet.
  exit /b 1
)
echo.
echo SYNC CHECK PASSED - critical final-fix files are current.
exit /b 0
