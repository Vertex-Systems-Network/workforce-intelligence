<?php

namespace Tests\Unit;

use App\Services\Commerce\CommerceUrlGuard;
use App\Support\PlanCatalog;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Protects Commerce V2 seller/platform and workspace-client billing boundaries without requiring a database. */
class CommerceV2ContractTest extends TestCase
{
    /** Verifies client payments and recurring invoicing are seller-controlled plan capabilities. */
    public function test_plan_capability_catalog_contains_workspace_client_commerce_features(): void
    {
        $catalog = collect(PlanCatalog::capabilities())->keyBy('key');
        $this->assertSame('boolean', $catalog->get('feature.client_payments')['value_type']);
        $this->assertSame('boolean', $catalog->get('feature.recurring_client_invoices')['value_type']);
        $this->assertTrue(PlanCatalog::DEFINITIONS['gold']['entitlements']['feature.client_payments']);
        $this->assertFalse(PlanCatalog::DEFINITIONS['free']['entitlements']['feature.client_payments']);
    }

    /** Verifies workspace gateway secrets remain encrypted/hidden and custom provider URLs require public HTTPS. */
    public function test_workspace_gateway_security_contract_is_present(): void
    {
        $model = file_get_contents(base_path('app/Models/WorkspaceClientPaymentGateway.php'));
        $service = file_get_contents(base_path('app/Services/ClientPortal/ClientPaymentGatewayService.php'));
        $this->assertStringContainsString("protected \$hidden=['credentials']", $model);
        $this->assertStringContainsString("'credentials'=>'encrypted:array'", $model);
        $this->assertStringContainsString('assertPublicHttps', $service);
        $this->assertStringNotContainsString('->assertSafe(', $service);

        $guard = new CommerceUrlGuard();
        $guard->assertPublicHttps('https://payments.example.com/checkout');
        $this->addToAssertionCount(1);
        try {
            $guard->assertPublicHttps('http://payments.example.com/checkout');
            $this->fail('Insecure client-commerce callback URL should be rejected.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }
    }

    /** Verifies the seller surface is separated from tenant navigation while Client Payments stays workspace scoped. */
    public function test_seller_and_workspace_commerce_surfaces_are_separated(): void
    {
        $app = file_get_contents(base_path('resources/js/app.tsx'));
        $manifest = file_get_contents(base_path('resources/js/navigation.manifest.json'));
        $seller = file_get_contents(base_path('resources/js/seller/SellerPlatformApp.tsx'));
        $routes = file_get_contents(base_path('routes/commerce.php'));
        $this->assertStringContainsString("path === '/seller'", $app);
        $this->assertStringContainsString("path.startsWith('/seller/')", $app);
        $this->assertStringContainsString('SellerPlatformApp', $app);
        $this->assertStringNotContainsString('["seller"]', $manifest);
        $this->assertStringContainsString('"client-commerce"', $manifest);
        $this->assertStringContainsString('platformOperator', $seller);
        $this->assertStringContainsString("middleware('platform.operator')", $routes);
        $this->assertStringContainsString("'/client-commerce'", $routes);
    }

    /** Verifies remote seller and workspace gateways use save-test-enable activation instead of a first-time enable deadlock. */
    public function test_remote_gateway_activation_contract_is_save_test_then_enable(): void
    {
        $workspace = file_get_contents(base_path('app/Http/Controllers/Api/V1/WorkspaceClientCommerceController.php'));
        $seller = file_get_contents(base_path('app/Http/Controllers/Api/V1/SellerCommerceController.php'));
        foreach ([$workspace, $seller] as $source) {
            $this->assertStringContainsString('$requiresActivationTest', $source);
            $this->assertStringContainsString("'enabled'=>\$enabled", $source);
            $this->assertStringContainsString("'is_default'=>\$enabled&&\$requestedDefault", $source);
            $this->assertStringContainsString("'activation_test'=>\$activationTest", $source);
        }
        $this->assertStringNotContainsString('Test this provider successfully before enabling it.', $seller);
    }

    /** Verifies Pay Now returns to the correct workspace portal and never a synthetic payments workspace slug. */
    public function test_client_portal_pay_now_return_contract_uses_workspace_slug(): void
    {
        $service = file_get_contents(base_path('app/Services/ClientPortal/ClientPaymentGatewayService.php'));
        $portal = file_get_contents(base_path('resources/js/client-portal/ClientPortalApp.tsx'));
        $this->assertStringContainsString("'/portal/'.rawurlencode((string)\$invoice->workspace->slug)", $service);
        $this->assertStringContainsString("'?payment_checkout='.\$session->id", $service);
        $this->assertStringNotContainsString('/portal/payments/return', $service);
        $this->assertStringContainsString('PaymentPanel', $portal);
        $this->assertStringContainsString('/payment-options', $portal);
        $this->assertStringContainsString('/payment-checkouts/', $portal);
    }
}
