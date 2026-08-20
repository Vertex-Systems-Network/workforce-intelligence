<?php
/** Dependency-free Block K source smoke for backup and disaster-recovery release contracts. */
$root=dirname(__DIR__);$required=[
 'database/migrations/2026_08_14_000500_create_operations_disaster_recovery.php','app/Services/Operations/SystemOperationsService.php',
 'app/Console/Commands/OperationsDisasterRecoveryDoctor.php','app/Console/Commands/RunSystemBackup.php','app/Console/Commands/RunDueSystemBackup.php',
 'app/Console/Commands/RestoreSystemBackup.php','app/Console/Commands/OperationsMaintenanceMode.php','app/Http/Controllers/Api/V1/PlatformOperationsController.php',
 'tests/Unit/OperationsDisasterRecoveryContractTest.php','tests/frontend/operations-disaster-recovery.test.mjs'];
foreach($required as $file)if(!is_file($root.'/'.$file)){fwrite(STDERR,"Missing Block K file: {$file}\n");exit(1);}
$service=file_get_contents($root.'/app/Services/Operations/SystemOperationsService.php');$routes=file_get_contents($root.'/routes/commerce.php');$console=file_get_contents($root.'/routes/console.php');
foreach(["hash('sha256',\$raw)",'readStream','minimum_verified_copies'] as $needle)if(!str_contains($service,$needle)){fwrite(STDERR,"Missing Block K service contract: {$needle}\n");exit(1);}
foreach(['/operations/backups','restore-requests'] as $needle)if(!str_contains($routes,$needle)){fwrite(STDERR,"Missing Block K route contract: {$needle}\n");exit(1);}
if(!str_contains($console,'workintel:backup-if-due')){fwrite(STDERR,"Missing scheduled Block K backup gate.\n");exit(1);}echo "Operations & Disaster Recovery Block K smoke: PASS\n";
