<?php
namespace App\Services\Commerce\Providers;
use App\Models\CommerceCheckoutSession;use App\Models\CommerceProviderConfig;use App\Models\CommerceRefund;use App\Models\BillingTransaction;use Illuminate\Http\Request;
/** Provides manual provider behavior within the WorkIntel application. */ class ManualProvider implements CommerceProvider
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly string $provider='manual'){}
    /** Handles the key operation for the current WorkIntel workflow. */ public function key():string{return $this->provider;}
    /** Handles the test operation for the current WorkIntel workflow. */ public function test(CommerceProviderConfig $config):array{return ['ok'=>true,'message'=>$this->provider==='bank_transfer'?'Bank transfer instructions are configured locally.':'Manual settlement is available.'];}
    /** Creates create checkout data for the requested workflow. */ public function createCheckout(CommerceProviderConfig $config,CommerceCheckoutSession $session,string $buyerEmail):array{return ['status'=>'pending','provider_session_id'=>$session->uuid,'checkout_url'=>null,'metadata'=>['instructions'=>$config->settings['instructions']??'Contact billing to complete payment.']];}
    /** Handles the refund operation for the current WorkIntel workflow. */ public function refund(CommerceProviderConfig $config,CommerceRefund $refund,?BillingTransaction $transaction):array{return ['status'=>'succeeded','provider_refund_id'=>'manual-'.$refund->uuid];}
    /** Handles the verify webhook operation for the current WorkIntel workflow. */ public function verifyWebhook(CommerceProviderConfig $config,Request $request,string $raw):bool{return false;}
    /** Handles the normalize webhook operation for the current WorkIntel workflow. */ public function normalizeWebhook(array $payload):?array{return null;}
}
