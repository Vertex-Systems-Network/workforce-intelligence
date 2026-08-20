<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards Block L observability schema, telemetry listeners, seller routes and diagnostics contracts. */
class ObservabilityAuditOperationsContractTest extends TestCase
{
    /** Assert centralized event capture, alerting, scheduler heartbeat and diagnostics surfaces are wired. */
    public function test_observability_and_audit_operations_contract_is_wired(): void
    {
        $migration=file_get_contents(base_path('database/migrations/2026_08_14_000600_create_observability_audit_operations.php'));
        $service=file_get_contents(base_path('app/Services/Observability/ObservabilityService.php'));
        $provider=file_get_contents(base_path('app/Providers/AppServiceProvider.php'));
        $bootstrap=file_get_contents(base_path('bootstrap/app.php'));
        $routes=file_get_contents(base_path('routes/commerce.php'));
        $console=file_get_contents(base_path('routes/console.php'));
        $seller=file_get_contents(base_path('resources/js/pages/SellerConsole.tsx'));
        foreach(['system_observability_events','system_observability_heartbeats','system_observability_alert_rules','system_observability_alerts'] as $table)$this->assertStringContainsString($table,$migration);
        $this->assertStringContainsString('recordException',$service);
        $this->assertStringContainsString('recordQuery',$service);
        $this->assertStringContainsString('Queue::failing',$provider);
        $this->assertStringContainsString('ObserveRequest::class',$bootstrap);
        $this->assertStringContainsString('/observability/diagnostics',$routes);
        $this->assertStringContainsString('workintel:observability-evaluate',$console);
        $this->assertStringContainsString('Observability & Audit Operations',$seller);
    }

    /** Assert persisted observability context is encrypted and sanitization guards credential-shaped keys. */
    public function test_observability_privacy_contract_is_present(): void
    {
        $event=file_get_contents(base_path('app/Models/SystemObservabilityEvent.php'));
        $service=file_get_contents(base_path('app/Services/Observability/ObservabilityService.php'));
        $diagnostics=file_get_contents(base_path('app/Services/Observability/DiagnosticsBundleService.php'));
        $this->assertStringContainsString("'context'=>'encrypted:array'",$event);
        $this->assertStringContainsString('authorization|cookie|credential',$service);
        $this->assertStringContainsString('serialized failed-job payloads are intentionally excluded',$diagnostics);
    }
}
