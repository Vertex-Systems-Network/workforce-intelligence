<?php
namespace App\Services\Commerce\Providers;
use App\Models\CommerceCheckoutSession;use App\Models\CommerceProviderConfig;use App\Models\CommerceRefund;use App\Models\BillingTransaction;use Illuminate\Http\Request;
/** Defines the commerce provider contract used by WorkIntel. */ interface CommerceProvider
{
    /** Handles the key operation for the current WorkIntel workflow. */ public function key():string;
    /** Handles the test operation for the current WorkIntel workflow. */ public function test(CommerceProviderConfig $config):array;
    /** Creates create checkout data for the requested workflow. */ public function createCheckout(CommerceProviderConfig $config,CommerceCheckoutSession $session,string $buyerEmail):array;
    /** Handles the refund operation for the current WorkIntel workflow. */ public function refund(CommerceProviderConfig $config,CommerceRefund $refund,?BillingTransaction $transaction):array;
    /** Handles the verify webhook operation for the current WorkIntel workflow. */ public function verifyWebhook(CommerceProviderConfig $config,Request $request,string $raw):bool;
    /** Handles the normalize webhook operation for the current WorkIntel workflow. */ public function normalizeWebhook(array $payload):?array;
}
