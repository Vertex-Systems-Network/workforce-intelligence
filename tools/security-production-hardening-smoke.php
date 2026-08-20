<?php
/** Dependency-free Block M source smoke for browser, upload, API-key and posture hardening. */
$root=dirname(__DIR__);$required=['app/Services/Security/UploadSecurityService.php','app/Services/Security/SecurityPostureService.php','app/Http/Controllers/Api/V1/PlatformSecurityController.php','app/Console/Commands/SecurityProductionDoctor.php','tests/Unit/SecurityProductionHardeningContractTest.php','tests/frontend/security-production-hardening.test.mjs'];
foreach($required as $file)if(!is_file($root.'/'.$file)){fwrite(STDERR,"Missing Block M file: {$file}\n");exit(1);}
$headers=file_get_contents($root.'/app/Http/Middleware/SecurityHeaders.php');$media=file_get_contents($root.'/app/Services/Media/MediaLibraryService.php');$routes=file_get_contents($root.'/routes/commerce.php');$provider=file_get_contents($root.'/app/Providers/AppServiceProvider.php');
foreach(['Content-Security-Policy','Cross-Origin-Opener-Policy','X-Permitted-Cross-Domain-Policies'] as $needle)if(!str_contains($headers,$needle)){fwrite(STDERR,"Missing header contract: {$needle}\n");exit(1);}
if(!str_contains($media,'quarantine/')||!str_contains($routes,'/security-posture')||!str_contains($provider,"RateLimiter::for('auth-login'")){fwrite(STDERR,"Block M wiring is incomplete.\n");exit(1);}echo "Security Production Hardening Block M smoke: PASS\n";
