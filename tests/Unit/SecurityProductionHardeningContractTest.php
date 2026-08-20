<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Verifies source-level Block M security contracts without requiring a database connection. */
class SecurityProductionHardeningContractTest extends TestCase
{
    /** Verify headers, upload inspection, named limits and platform-operator posture endpoints remain wired. */
    public function test_security_hardening_contracts_are_present(): void
    {
        $root = dirname(__DIR__, 2);
        $headers = file_get_contents($root.'/app/Http/Middleware/SecurityHeaders.php');
        $uploads = file_get_contents($root.'/app/Services/Security/UploadSecurityService.php');
        $media = file_get_contents($root.'/app/Services/Media/MediaLibraryService.php');
        $provider = file_get_contents($root.'/app/Providers/AppServiceProvider.php');
        $routes = file_get_contents($root.'/routes/commerce.php');
        $this->assertStringContainsString('Content-Security-Policy', $headers);
        $this->assertStringContainsString('FILEINFO_MIME_TYPE', $uploads);
        $this->assertStringContainsString('quarantine/', $media);
        $this->assertStringContainsString("RateLimiter::for('auth-login'", $provider);
        $this->assertStringContainsString('/security-posture', $routes);
    }

    /** Verify password creation/reset rules use the strengthened production baseline. */
    public function test_password_rules_require_twelve_character_mixed_credentials(): void
    {
        $root = dirname(__DIR__, 2);
        $register = file_get_contents($root.'/app/Http/Requests/Auth/RegisterRequest.php');
        $lifecycle = file_get_contents($root.'/app/Http/Controllers/Api/V1/UserLifecycleController.php');
        $this->assertStringContainsString('Password::min(12)->mixedCase()->letters()->numbers()->symbols()', $register);
        $this->assertStringContainsString('PasswordRule::min(12)->mixedCase()->letters()->numbers()->symbols()', $lifecycle);
    }
}
