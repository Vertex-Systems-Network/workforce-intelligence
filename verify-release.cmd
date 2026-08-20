@echo off
setlocal EnableExtensions
cd /d %~dp0

echo ============================================================
echo WorkIntel - NON-DESTRUCTIVE RELEASE VERIFICATION
echo ============================================================

where php >nul 2>nul || (echo ERROR: PHP not found in PATH.& exit /b 1)
where composer >nul 2>nul || (echo ERROR: Composer not found in PATH.& exit /b 1)
where node >nul 2>nul || (echo ERROR: Node.js not found in PATH.& exit /b 1)
where npm >nul 2>nul || (echo ERROR: npm not found in PATH.& exit /b 1)

call :gate "Prepare Laravel runtime directories" "php tools\prepare-runtime.php" || exit /b 1
call :gate "Runtime directory self-healing" "php tools\runtime-directory-recovery-smoke.php" || exit /b 1
call :gate "Block J runtime closure preflight" "php tools\runtime-closure-preflight.php" || exit /b 1
call :gate "Runtime doctor" "php workintel-doctor.php" || exit /b 1
call :gate "Release source smoke" "php tools\release-smoke.php" || exit /b 1
call :gate "Source integrity" "node tools\audit-source-integrity.mjs" || exit /b 1
call :gate "PHP documentation" "php tools\audit-php-documentation.php" || exit /b 1
call :gate "JS/TS documentation" "node tools\audit-js-documentation.mjs" || exit /b 1
call :gate "JSX component binding audit" "node tools\audit-jsx-component-bindings.mjs" || exit /b 1
call :gate "M2 WorkIntel Design System audit" "node tools\design-system-audit.mjs" || exit /b 1
call :gate "M3 Application Shell audit" "node tools\application-shell-audit.mjs" || exit /b 1
call :gate "M4 Shared UX Systems audit" "node tools\shared-ux-audit.mjs" || exit /b 1
call :gate "M5 Core Workforce audit" "node tools\core-workforce-m5-audit.mjs" || exit /b 1
call :gate "M6 Business/Admin audit" "node tools\business-admin-m6-audit.mjs" || exit /b 1
call :gate "M7 Media DAM V3 audit" "node tools\media-dam-v3-audit.mjs" || exit /b 1
call :gate "M8 Website Studio V3 audit" "node tools\website-studio-v3-audit.mjs" || exit /b 1
call :gate "M9 Document Studio V6 audit" "node tools\document-studio-v6-audit.mjs" || exit /b 1
call :gate "M9 Document Studio V6 contract PHPUnit" "php artisan test --filter=DocumentStudioV6ContractTest" || exit /b 1
call :gate "M9 Document Studio V6 feature flow" "php artisan test --filter=DocumentStudioV6FlowTest" || exit /b 1
call :gate "M10 Chat & Collaboration V4 audit" "node tools\chat-collaboration-v4-audit.mjs" || exit /b 1
call :gate "M10 Chat & Collaboration V4 contract PHPUnit" "php artisan test --filter=ChatCollaborationV4ContractTest" || exit /b 1
call :gate "M10 Chat & Collaboration V4 feature flow" "php artisan test --filter=ChatCollaborationV4FlowTest" || exit /b 1
call :gate "M11 Role UX + Help audit" "node tools\role-ux-help-m11-audit.mjs" || exit /b 1
call :gate "M12 final certification source audit" "node tools\m12-final-certification-audit.mjs" || exit /b 1
call :gate "M12 source performance budget" "node tools\performance-budget-audit.mjs" || exit /b 1
call :gate "M11 Role UX + Help contract PHPUnit" "php artisan test --filter=RoleUxHelpM11ContractTest" || exit /b 1
call :gate "M11 Role UX + Help feature flow" "php artisan test --filter=RoleUxHelpM11FlowTest" || exit /b 1
call :gate "M12 Final Certification contract PHPUnit" "php artisan test --filter=FinalCertificationM12ContractTest" || exit /b 1
call :gate "M12 Final Certification feature flow" "php artisan test --filter=FinalCertificationM12FlowTest" || exit /b 1
call :gate "Module architecture audit" "node tools\module-architecture-audit.mjs" || exit /b 1
call :gate "Migration source integrity" "php tools\audit-migrations.php" || exit /b 1
call :gate "Database schema naming audit" "php tools\database-schema-naming-audit.php" || exit /b 1
call :gate "Block N Laragon regression smoke" "php tools\block-n-laragon-regression-smoke.php" || exit /b 1
call :gate "Block N final source sync" "php tools\block-n-final-sync-check.php" || exit /b 1
call :gate "UI/runtime source smoke" "php tools\ui-runtime-stabilization-smoke.php" || exit /b 1
call :gate "DataGrid V2 source smoke" "php tools\data-grid-v2-smoke.php" || exit /b 1
call :gate "Data Lifecycle + Media source smoke" "php tools\data-lifecycle-media-smoke.php" || exit /b 1
call :gate "Localization & Navigation V2 source smoke" "php tools\localization-navigation-v2-smoke.php" || exit /b 1
call :gate "Localization Full Page Copy E.1 source smoke" "php tools\localization-page-copy-e1-smoke.php" || exit /b 1
call :gate "Commerce V2 source smoke" "php tools\commerce-v2-smoke.php" || exit /b 1
call :gate "Document Studio V4 source smoke" "php tools\document-studio-v4-smoke.php" || exit /b 1
call :gate "Website & Portal Builder source smoke" "php tools\website-portal-builder-smoke.php" || exit /b 1
call :gate "Production certification source smoke" "php tools\production-certification-smoke.php" || exit /b 1
call :gate "Block J runtime closure source smoke" "php tools\runtime-closure-smoke.php" || exit /b 1
call :gate "Operations & Disaster Recovery source smoke" "php tools\operations-disaster-recovery-smoke.php" || exit /b 1
call :gate "Observability & Audit Operations source smoke" "php tools\observability-audit-operations-smoke.php" || exit /b 1
call :gate "Chat V2.1 source smoke" "php tools\chat-stabilization-smoke.php" || exit /b 1
call :gate "Chat V2.2 source smoke" "php tools\chat-professional-messaging-smoke.php" || exit /b 1
call :gate "Chat V2.3 source smoke" "php tools\chat-workspace-collaboration-smoke.php" || exit /b 1
call :gate "Chat V2.4 source smoke" "php tools\chat-enterprise-collaboration-smoke.php" || exit /b 1
call :gate "Chat V2.5 source smoke" "php tools\chat-performance-certification-smoke.php" || exit /b 1

if not exist vendor\autoload.php (
  call :gate "Composer install" "composer install --no-interaction --prefer-dist" || exit /b 1
)
call :gate "Composer lock validation" "composer validate --no-check-publish" || exit /b 1
call :gate "Composer platform requirements" "composer check-platform-reqs" || exit /b 1
call :gate "PHP runtime preflight" "php tools\runtime-preflight.php" || exit /b 1

if not exist .env (
  echo ERROR: .env is missing. Copy .env.example and configure the existing database first.
  exit /b 1
)

call :gate "Clear Laravel caches" "php artisan optimize:clear" || exit /b 1
call :gate "Migration doctor before upgrade" "php artisan workintel:migration-doctor" || exit /b 1
call :gate "Additive migrations" "php artisan migrate --force" || exit /b 1
call :gate "Migration status" "php artisan migrate:status" || exit /b 1
call :gate "UI/runtime doctor" "php artisan workintel:ui-runtime-doctor" || exit /b 1
call :gate "Migration recovery smoke" "php tools\migration-recovery-smoke.php" || exit /b 1
call :gate "Seeder source integrity" "php tools\audit-seeders.php" || exit /b 1
call :gate "Pure unit smoke" "php tools\run-unit-smoke.php" || exit /b 1
call :gate "UI/runtime contract PHPUnit" "php artisan test --filter=UiRuntimeStabilizationContractTest" || exit /b 1
call :gate "User page preference feature flow" "php artisan test --filter=UserPagePreferenceFlowTest" || exit /b 1
call :gate "DataGrid V2 server contract PHPUnit" "php artisan test --filter=DataGridRequestTest" || exit /b 1
call :gate "Data Lifecycle + Media contract PHPUnit" "php artisan test --filter=DataLifecycleMediaContractTest" || exit /b 1
call :gate "Data Lifecycle + Media feature flow" "php artisan test --filter=DataLifecycleMediaFlowTest" || exit /b 1
call :gate "Data Lifecycle + Media doctor" "php artisan workintel:block-d-doctor" || exit /b 1
call :gate "Localization & Navigation V2 contract PHPUnit" "php artisan test --filter=LocalizationNavigationV2ContractTest" || exit /b 1
call :gate "Localization Full Page Copy E.1 contract PHPUnit" "php artisan test --filter=LocalizationPageCopyE1ContractTest" || exit /b 1
call :gate "Commerce V2 contract PHPUnit" "php artisan test --filter=CommerceV2ContractTest" || exit /b 1
call :gate "Commerce V2 feature flow" "php artisan test --filter=CommerceV2FlowTest" || exit /b 1
call :gate "Commerce V2 doctor" "php artisan workintel:commerce-v2-doctor" || exit /b 1
call :gate "Document Studio V4 contract PHPUnit" "php artisan test --filter=DocumentStudioV4ContractTest" || exit /b 1
call :gate "Document Studio V4 feature flow" "php artisan test --filter=DocumentStudioV4FlowTest" || exit /b 1
call :gate "Document Studio V4 doctor" "php artisan workintel:document-v4-doctor --json" || exit /b 1
call :gate "Website Builder contract PHPUnit" "php artisan test --filter=WebsitePortalBuilderContractTest" || exit /b 1
call :gate "Website Builder feature flow" "php artisan test --filter=WebsitePortalBuilderFlowTest" || exit /b 1
call :gate "Website Builder doctor" "php artisan workintel:website-builder-doctor --json" || exit /b 1
call :gate "Production certification contract PHPUnit" "php artisan test --filter=ProductionCertificationContractTest" || exit /b 1
call :gate "Production certification feature flow" "php artisan test --filter=ProductionCertificationFlowTest" || exit /b 1
call :gate "Operations & Disaster Recovery contract PHPUnit" "php artisan test --filter=OperationsDisasterRecoveryContractTest" || exit /b 1
call :gate "Operations & Disaster Recovery doctor" "php artisan workintel:operations-doctor --json" || exit /b 1
call :gate "Observability & Audit Operations contract PHPUnit" "php artisan test --filter=ObservabilityAuditOperationsContractTest" || exit /b 1
call :gate "Observability & Audit Operations feature flow" "php artisan test --filter=ObservabilityAuditOperationsFlowTest" || exit /b 1
call :gate "Observability doctor" "php artisan workintel:observability-doctor --json" || exit /b 1
call :gate "Security Production Hardening source smoke" "php tools\security-production-hardening-smoke.php" || exit /b 1
call :gate "Accessibility & Browser Certification source smoke" "php tools\accessibility-browser-smoke.php" || exit /b 1
call :gate "Studio + unified UX source smoke" "php tools\studio-unified-ux-smoke.php" || exit /b 1
call :gate "Platform UX stabilization source smoke" "php tools\platform-ux-stabilization-smoke.php" || exit /b 1
call :gate "Platform UX stabilization contract PHPUnit" "php artisan test --filter=PlatformUxStabilizationContractTest" || exit /b 1
call :gate "Security Production Hardening contract PHPUnit" "php artisan test --filter=SecurityProductionHardeningContractTest" || exit /b 1
call :gate "Security Production Hardening feature flow" "php artisan test --filter=SecurityProductionHardeningFlowTest" || exit /b 1
call :gate "Security production doctor" "php artisan workintel:security-doctor --json" || exit /b 1
call :gate "Accessibility & Browser Certification contract PHPUnit" "php artisan test --filter=AccessibilityBrowserCertificationContractTest" || exit /b 1
call :gate "Accessibility browser doctor" "php artisan workintel:accessibility-doctor --json" || exit /b 1
call :gate "PHPUnit unit suite" "php artisan test --testsuite=Unit" || exit /b 1
call :gate "Chat V2.1 targeted PHPUnit" "php artisan test --filter=ChatStabilizationContractTest" || exit /b 1
call :gate "Chat V2.2 contract PHPUnit" "php artisan test --filter=ChatProfessionalMessagingContractTest" || exit /b 1
call :gate "Chat V2.2 feature flow" "php artisan test --filter=ChatProfessionalMessagingFlowTest" || exit /b 1
call :gate "Chat V2.3 contract PHPUnit" "php artisan test --filter=ChatWorkspaceCollaborationContractTest" || exit /b 1
call :gate "Chat V2.3 feature flow" "php artisan test --filter=ChatWorkspaceCollaborationFlowTest" || exit /b 1
call :gate "Chat V2.3 doctor" "php artisan workintel:chat-v2.3-doctor" || exit /b 1
call :gate "Chat V2.4 contract PHPUnit" "php artisan test --filter=ChatEnterpriseCollaborationContractTest" || exit /b 1
call :gate "Chat V2.4 feature flow" "php artisan test --filter=ChatEnterpriseCollaborationFlowTest" || exit /b 1
call :gate "Chat V2.4 doctor" "php artisan workintel:chat-v2.4-doctor" || exit /b 1
call :gate "Chat V2.5 contract PHPUnit" "php artisan test --filter=ChatPerformanceCertificationContractTest" || exit /b 1
call :gate "Chat V2.5 feature flow" "php artisan test --filter=ChatPerformanceCertificationFlowTest" || exit /b 1
call :gate "Chat V2.5 doctor" "php artisan workintel:chat-v2.5-doctor" || exit /b 1
call :gate "Chat V2 doctor" "php artisan workintel:chat-v2-doctor" || exit /b 1
call :gate "Chat collaboration flow" "php artisan test --filter=ChatCollaborationFlowTest" || exit /b 1
call :gate "Full PHPUnit suite" "php artisan test" || exit /b 1

if exist package-lock.json (
  call :gate "npm clean install" "npm ci --no-audit --no-fund" || exit /b 1
) else (
  call :gate "npm install --no-audit --no-fund" "npm install --no-audit --no-fund" || exit /b 1
)
call :gate "npm frontend tests" "npm test" || exit /b 1
call :gate "Accessibility source audit" "npm run accessibility:audit" || exit /b 1
call :gate "Playwright Chromium and Firefox engines" "npx playwright install chromium firefox" || exit /b 1
call :gate "TypeScript typecheck" "npm run typecheck" || exit /b 1
call :gate "Production frontend build" "npm run build" || exit /b 1
call :gate "M12 production performance budget" "npm run performance:audit:build" || exit /b 1
call :gate "M12 final Laravel doctor" "php artisan workintel:final-certification --json --require-build" || exit /b 1
call :gate "Browser executable doctor" "node tools\e2e-browser-doctor.mjs" || exit /b 1
call :gate "Production doctor with build" "php artisan workintel:production-doctor --json --require-build" || exit /b 1
call :gate "Browser public certification" "npm run test:e2e:public" || exit /b 1
call :gate "Browser accessibility certification" "npm run test:e2e:accessibility" || exit /b 1
if /I "%WORKINTEL_REQUIRE_CROSS_BROWSER%"=="1" (
  call :gate "Actual Chrome Edge Firefox certification" "npm run test:e2e:cross-browser" || exit /b 1
)
call :gate "Route boot" "php artisan route:list --except-vendor" || exit /b 1
call :gate "Module route ownership audit" "php tools\module-route-audit.php" || exit /b 1
call :gate "Scheduler boot" "php artisan schedule:list" || exit /b 1

echo.
echo ============================================================
echo WORKINTEL RELEASE VERIFICATION PASSED
echo ============================================================
exit /b 0

:gate
echo.
echo [RUN] %~1
call %~2
if errorlevel 1 (
  echo [FAIL] Verification stopped at the first failing gate.
  exit /b 1
)
echo [PASS]
exit /b 0
