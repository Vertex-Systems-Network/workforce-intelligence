<?php
/** Dependency-free Block L source smoke for observability, alerting and diagnostics release contracts. */
$root=dirname(__DIR__);$required=[
 'database/migrations/2026_08_14_000600_create_observability_audit_operations.php','app/Services/Observability/ObservabilityService.php','app/Services/Observability/DiagnosticsBundleService.php',
 'app/Console/Commands/ObservabilityDoctor.php','app/Http/Middleware/ObserveRequest.php','app/Http/Controllers/Api/V1/PlatformObservabilityController.php',
 'tests/Unit/ObservabilityAuditOperationsContractTest.php','tests/Feature/ObservabilityAuditOperationsFlowTest.php','tests/frontend/observability-audit-operations.test.mjs'];
foreach($required as $file)if(!is_file($root.'/'.$file)){fwrite(STDERR,"Missing Block L file: {$file}\n");exit(1);}
$service=file_get_contents($root.'/app/Services/Observability/ObservabilityService.php');$routes=file_get_contents($root.'/routes/commerce.php');$console=file_get_contents($root.'/routes/console.php');
foreach(['recordException','recordQuery','evaluateAlerts','sanitize','failedJobs'] as $needle)if(!str_contains($service,$needle)){fwrite(STDERR,"Missing Block L service contract: {$needle}\n");exit(1);}
foreach(['/observability/diagnostics','/observability/evaluate'] as $needle)if(!str_contains($routes,$needle)){fwrite(STDERR,"Missing Block L route contract: {$needle}\n");exit(1);}
if(!str_contains($console,'workintel:observability-evaluate')||!str_contains($console,'workintel:observability-prune')){fwrite(STDERR,"Missing scheduled Block L maintenance.\n");exit(1);}echo "Observability & Audit Operations Block L smoke: PASS\n";
