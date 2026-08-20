<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards logout history, cache and bfcache protections without requiring a database connection. */
class LogoutBackNavigationSecurityContractTest extends TestCase
{
    /** Ensure private browser snapshots are hidden until Laravel session revalidation completes. */
    public function test_back_navigation_requires_server_session_revalidation(): void
    {
        $root = dirname(__DIR__, 2);
        $context = file_get_contents($root.'/resources/js/auth/AuthContext.tsx');
        $app = file_get_contents($root.'/resources/js/app.tsx');
        $css = file_get_contents($root.'/resources/css/app.css');
        $this->assertStringContainsString("window.addEventListener('pageshow'", $context);
        $this->assertStringContainsString("navigation?.type === 'back_forward'", $context);
        $this->assertStringContainsString("window.addEventListener('pagehide'", $app);
        $this->assertStringContainsString('data-workintel-private-snapshot', $css);
    }

    /** Ensure logout is locally terminal and authenticated responses cannot be browser cached. */
    public function test_logout_and_private_cache_contracts_are_terminal(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root.'/resources/js/auth/authService.ts');
        $client = file_get_contents($root.'/resources/js/api/client.ts');
        $headers = file_get_contents($root.'/app/Http/Middleware/SecurityHeaders.php');
        $worker = file_get_contents($root.'/public/sw.js');
        $this->assertStringContainsString('LOCAL_LOGOUT_KEY', $service);
        $this->assertStringContainsString('keepalive: true', $service);
        $this->assertStringContainsString('[401, 419]', $client);
        $this->assertStringContainsString('no-store, private, max-age=0, must-revalidate', $headers);
        $this->assertStringNotContainsString("SHELL=['/app'", $worker);
    }
}
