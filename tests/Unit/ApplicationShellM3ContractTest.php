<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the M3 module-first application shell, discovery and tenant/operator boundary. */
class ApplicationShellM3ContractTest extends TestCase
{
    /** Ensure tenant navigation follows the M1 business-module taxonomy with no Platform Console leakage. */
    public function test_navigation_uses_business_modules_and_keeps_platform_console_separate(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode((string) file_get_contents($root.'/resources/js/navigation.manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $architecture = json_decode((string) file_get_contents($root.'/docs/architecture/workintel-modules.json'), true, flags: JSON_THROW_ON_ERROR);
        $allowed = array_column($architecture['modules'], 'id');
        $allowed[] = 'account-support';
        foreach ($manifest as $role => $groups) {
            foreach ($groups as $group) {
                $this->assertContains($group['id'], $allowed, $role.' has an unknown M3 module group.');
                foreach ($group['items'] as $item) {
                    $this->assertNotSame('platform', $item[0], 'Platform Console must not be in tenant navigation.');
                    $target = $architecture['screenMap'][$item[0]]['target'];
                    $this->assertSame($target === 'account-support' ? 'account-support' : $target, $group['id']);
                }
            }
        }
    }

    /** Ensure module homes, personal discovery and safe browser-history contracts are wired into the shell. */
    public function test_shell_explains_context_and_supports_module_navigation(): void
    {
        $root = dirname(__DIR__, 2);
        $home = (string) file_get_contents($root.'/resources/js/components/ModuleHome.tsx');
        $preferences = (string) file_get_contents($root.'/resources/js/shellPreferences.ts');
        $router = (string) file_get_contents($root.'/resources/js/shellNavigation.ts');
        $app = (string) file_get_contents($root.'/resources/js/WorkforceApp.tsx');
        foreach (['What you can do here','Favorites','Recent'] as $needle) $this->assertStringContainsString($needle, $home);
        foreach (['workspaceId','userId','toggleShellFavorite','recordRecentShellPage'] as $needle) $this->assertStringContainsString($needle, $preferences);
        foreach (['shellDestinationFromLocation','writeShellHistory','window.history.pushState','module/'] as $needle) $this->assertStringContainsString($needle, $router);
        foreach (['activeModule','navigateModule','ModuleHome','mobileOpen'] as $needle) $this->assertStringContainsString($needle, $app);
    }

    /** Ensure global discovery remains workspace, permission and WorkScope constrained. */
    public function test_global_entity_search_is_scope_and_permission_aware(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Api/V1/GlobalSearchController.php');
        $routes = (string) file_get_contents($root.'/routes/api.php');
        foreach (['scopePeople','scopeProjects','scopeTasks','clients.view','media.view','workspace_id'] as $needle) $this->assertStringContainsString($needle, $controller);
        $this->assertStringContainsString("Route::get('/search', GlobalSearchController::class)", $routes);
    }
}
