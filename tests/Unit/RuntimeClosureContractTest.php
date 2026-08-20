<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Verifies the Block J real-runtime certification and safety contracts remain wired. */
class RuntimeClosureContractTest extends TestCase
{
    /** Ensure the runtime closure captures diagnostics and guards destructive resets. */
    public function test_runtime_closure_tools_and_safety_guard_are_present(): void
    {
        $runner = file_get_contents(base_path('tools/run-runtime-closure.ps1'));
        $guard = file_get_contents(base_path('tools/assert-disposable-database.php'));
        $preflight = file_get_contents(base_path('tools/runtime-closure-preflight.php'));
        $this->assertStringContainsString('runtime-closure', $runner);
        $this->assertStringContainsString('WORKINTEL_RESET_CONFIRM', $runner);
        $this->assertStringContainsString("APP_ENV", $guard);
        $this->assertStringContainsString('production', $guard);
        $this->assertStringContainsString('phpunit_sqlite', $preflight);
        $this->assertStringContainsString('php_ini', $preflight);
    }

    /** Ensure local certification retains a seeded seller operator while production has no implicit operator. */
    public function test_platform_operator_blank_env_uses_only_non_production_seed_fallback(): void
    {
        $config = file_get_contents(base_path('config/workintel.php'));
        $this->assertStringContainsString("trim((string) env('WORKINTEL_PLATFORM_OPERATOR_EMAILS', ''))", $config);
        $this->assertStringContainsString("'owner@acme.test'", $config);
        $this->assertStringContainsString("env('APP_ENV', 'production') !== 'production'", $config);
    }
    /** Ensure the two previously observed Laragon runtime regressions remain closed. */
    public function test_stateless_registration_and_gold_payroll_runtime_regressions_are_guarded(): void
    {
        $auth = file_get_contents(base_path('app/Http/Controllers/Api/V1/AuthController.php'));
        $plans = file_get_contents(base_path('app/Support/PlanCatalog.php'));
        $this->assertMatchesRegularExpression('/Auth::login\(\$user\);[\s\S]{0,180}\$request->hasSession\(\)/', $auth);
        $this->assertMatchesRegularExpression("/'gold'[\s\S]{0,1800}'feature\.payroll'\s*=>\s*true/", $plans);
    }

    /** Ensure normal web and Artisan bootstrap self-heal empty runtime directories before Laravel writes files. */
    public function test_runtime_directories_self_heal_before_framework_boot(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $guard = file_get_contents(base_path('bootstrap/runtime.php'));
        $prepare = file_get_contents(base_path('tools/prepare-runtime.php'));

        $this->assertStringContainsString("require_once __DIR__.'/runtime.php'", $bootstrap);
        $this->assertStringContainsString('workintel_prepare_runtime_directories(dirname(__DIR__))', $bootstrap);
        $this->assertStringContainsString("'storage/framework/sessions'", $guard);
        $this->assertStringContainsString("'storage/framework/views'", $guard);
        $this->assertStringContainsString("'storage/framework/cache/data'", $guard);
        $this->assertStringContainsString('workintel_prepare_runtime_directories($root)', $prepare);
    }

}
