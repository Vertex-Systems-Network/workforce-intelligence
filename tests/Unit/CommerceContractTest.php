<?php

namespace Tests\Unit;

use App\Services\Commerce\CommerceProviderRegistry;
use App\Services\Commerce\CommerceUrlGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Provides p11 commerce contract test behavior within the WorkIntel application. */ class CommerceContractTest extends TestCase
{
    /** Handles the test provider registry has manual bank and major hosted adapters operation for the current WorkIntel workflow. */ public function test_provider_registry_has_manual_bank_and_major_hosted_adapters(): void
    {
        $keys=array_column((new CommerceProviderRegistry())->catalog(),'key');
        foreach(['manual','bank_transfer','stripe','paypal','paddle','custom_http'] as $key)$this->assertContains($key,$keys);
    }

    /** Handles the test custom commerce urls reject private network targets operation for the current WorkIntel workflow. */ public function test_custom_commerce_urls_reject_private_network_targets(): void
    {
        $guard=new CommerceUrlGuard();
        foreach(['http://example.com/pay','https://127.0.0.1/pay','https://localhost/pay','https://10.0.0.1/pay'] as $url){
            try{$guard->assertPublicHttps($url);$this->fail("{$url} should be rejected.");}catch(RuntimeException){$this->addToAssertionCount(1);}
        }
    }

    /** Handles the test seller console is platform operator gated and buyer checkout is workspace scoped operation for the current WorkIntel workflow. */ public function test_seller_console_is_platform_operator_gated_and_buyer_checkout_is_workspace_scoped(): void
    {
        $routes=file_get_contents(base_path('routes/commerce.php'));
        $this->assertStringContainsString("middleware('platform.operator')",$routes);
        $this->assertStringContainsString('ResolveWorkspace::class',$routes);
        $this->assertStringContainsString("'/commerce/checkout'",$routes);
        $this->assertStringContainsString("'/commerce/webhooks/{provider}'",$routes);
    }

    /** Handles the test seller plan and tax controls include trials and regional tax operation for the current WorkIntel workflow. */ public function test_seller_plan_and_tax_controls_include_trials_and_regional_tax(): void
    {
        $controller=file_get_contents(base_path('app/Http/Controllers/Api/V1/SellerCommerceController.php'));
        $checkout=file_get_contents(base_path('app/Services/Commerce/CommerceCheckoutService.php'));
        $ui=file_get_contents(base_path('resources/js/pages/SellerConsole.tsx'));
        $this->assertStringContainsString("'trial_days'=>'sometimes|integer|min:0|max:365'",$controller);
        $this->assertStringContainsString("state_region",$checkout);
        $this->assertStringContainsString('Trial days', $ui);
        $this->assertStringContainsString('seller-capability-matrix', $ui);
        $this->assertStringContainsString('State / region', $ui);
    }

    /** Handles the test provider secrets are hidden and encrypted at rest contract operation for the current WorkIntel workflow. */ public function test_provider_secrets_are_hidden_and_encrypted_at_rest_contract(): void
    {
        $model=file_get_contents(base_path('app/Models/CommerceProviderConfig.php'));
        $this->assertSame(1,preg_match('/protected\s+\$hidden\s*=\s*\[\s*[\'"]credentials[\'"]\s*\]/',$model));
        $this->assertSame(1,preg_match('/[\'"]credentials[\'"]\s*=>\s*[\'"]encrypted:array[\'"]/',$model));
        $controller=file_get_contents(base_path('app/Http/Controllers/Api/V1/SellerCommerceController.php'));
        $this->assertSame(1,preg_match('/[\'"]has_credentials[\'"]\s*=>\s*!\s*empty\(\s*\$p->credentials\s*\)/',$controller));
        $this->assertSame(0,preg_match('/[\'"]credentials[\'"]\s*=>\s*\$p->credentials/',$controller));
    }
}
