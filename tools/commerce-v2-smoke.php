<?php

/** Dependency-free Commerce V2 source contract used before Composer/Laravel runtime is available. */
$root = dirname(__DIR__);
$checks = [
    'migration' => ['database/migrations/2026_08_14_000200_create_commerce_v2.php', ['workspace_client_payment_gateways', 'client_payment_checkout_sessions', 'client_invoice_schedules', 'client_payment_provider_tx_uq']],
    'seller routes' => ['routes/commerce.php', ["middleware('platform.operator')", "'/plans/{subscriptionPlan}/entitlements'", "'/client-commerce'"]],
    'portal routes' => ['routes/api.php', ['payment-options', "'/invoices/{clientInvoice}/checkout'", 'payment-checkouts/{checkout}', 'feature.client_payments']],
    'plan catalog' => ['app/Support/PlanCatalog.php', ['feature.client_payments', 'feature.recurring_client_invoices', 'sync(bool $overwrite=false)']],
    'gateway security' => ['app/Models/WorkspaceClientPaymentGateway.php', ["protected \$hidden=['credentials']", "'credentials'=>'encrypted:array'"]],
    'gateway service' => ['app/Services/ClientPortal/ClientPaymentGatewayService.php', ['assertPublicHttps', 'payment_checkout=', 'recordPayment']],
    'seller shell' => ['resources/js/seller/SellerPlatformApp.tsx', ['WorkIntel Seller Platform', 'platformOperator', 'seller-shell']],
    'safe remote activation' => ['app/Http/Controllers/Api/V1/SellerCommerceController.php', ['$requiresActivationTest', "'activation_test'=>\$activationTest"]],
    'workspace client commerce' => ['resources/js/pages/ClientCommerce.tsx', ['Client Payments', 'Recurring invoices', 'Allowed Pay Now gateways', 'activation_test', 'remains disabled']],
    'portal pay now' => ['resources/js/client-portal/ClientPortalApp.tsx', ['PaymentPanel', 'Pay now', 'payment-options', 'Check status']],
];

$failures = [];
foreach ($checks as $label => [$relative, $needles]) {
    $path = $root.DIRECTORY_SEPARATOR.$relative;
    if (! is_file($path)) {
        $failures[] = "Missing {$relative}";
        continue;
    }
    $source = file_get_contents($path);
    foreach ($needles as $needle) if (! str_contains($source, $needle)) $failures[] = "{$label} missing {$needle}";
}

if ($failures) {
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: {$failure}".PHP_EOL);
    exit(1);
}

echo 'Commerce V2 source smoke: PASS'.PHP_EOL;
