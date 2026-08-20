<?php
namespace App\Services\Commerce\Providers;
use App\Models\BillingTransaction;use App\Models\CommerceCheckoutSession;use App\Models\CommerceProviderConfig;use App\Models\CommerceRefund;use Illuminate\Http\Request;use Illuminate\Support\Facades\Http;use RuntimeException;
/** Provides stripe provider behavior within the WorkIntel application. */ class StripeProvider implements CommerceProvider
{
    /** Handles the key operation for the current WorkIntel workflow. */ public function key():string{return 'stripe';}
    /** Handles the base operation for the current WorkIntel workflow. */ private function base():string{return 'https://api.stripe.com';}
    /** Handles the secret operation for the current WorkIntel workflow. */ private function secret(CommerceProviderConfig $c):string{$v=$c->credentials['secret_key']??'';if(!$v)throw new RuntimeException('Stripe secret key is not configured.');return $v;}
    /** Handles the test operation for the current WorkIntel workflow. */ public function test(CommerceProviderConfig $c):array{$r=Http::withToken($this->secret($c))->timeout(10)->get($this->base().'/v1/account');return ['ok'=>$r->successful(),'message'=>$r->successful()?'Stripe account connection verified.':'Stripe connection failed: '.$r->status()];}
    /** Creates create checkout data for the requested workflow. */ public function createCheckout(CommerceProviderConfig $c,CommerceCheckoutSession $s,string $email):array
    {
        $key=$s->plan->slug.'.'.$s->billing_interval;$price=$c->settings['price_map'][$key]??null;if(!$price)throw new RuntimeException("Stripe price mapping missing for {$key}.");
        $success=$c->settings['success_url']??config('app.url').'/app?checkout=success';$cancel=$c->settings['cancel_url']??config('app.url').'/app?checkout=cancel';
        $r=Http::withToken($this->secret($c))->asForm()->timeout(15)->post($this->base().'/v1/checkout/sessions',[
            'mode'=>'subscription','success_url'=>$success,'cancel_url'=>$cancel,'client_reference_id'=>$s->uuid,'customer_email'=>$email,
            'line_items[0][price]'=>$price,'line_items[0][quantity]'=>$s->seat_quantity,'metadata[checkout_uuid]'=>$s->uuid,'subscription_data[metadata][workspace_id]'=>$s->workspace_id,
        ]);
        if(!$r->successful())throw new RuntimeException('Stripe checkout failed: '.($r->json('error.message')?:$r->status()));$j=$r->json();return ['status'=>'redirect','provider_session_id'=>$j['id']??null,'checkout_url'=>$j['url']??null,'metadata'=>[]];
    }
    /** Handles the refund operation for the current WorkIntel workflow. */ public function refund(CommerceProviderConfig $c,CommerceRefund $refund,?BillingTransaction $txn):array
    {
        $pid=$txn?->provider_transaction_id;if(!$pid)throw new RuntimeException('A Stripe payment transaction is required for refund.');
        $r=Http::withToken($this->secret($c))->asForm()->post($this->base().'/v1/refunds',['payment_intent'=>$pid,'amount'=>(int)round((float)$refund->amount*100),'metadata[refund_uuid]'=>$refund->uuid]);
        if(!$r->successful())throw new RuntimeException('Stripe refund failed: '.($r->json('error.message')?:$r->status()));return ['status'=>($r->json('status')==='succeeded'?'succeeded':'processing'),'provider_refund_id'=>$r->json('id')];
    }
    /** Handles the verify webhook operation for the current WorkIntel workflow. */ public function verifyWebhook(CommerceProviderConfig $c,Request $request,string $raw):bool
    {
        $secret=$c->credentials['webhook_secret']??'';$header=$request->header('Stripe-Signature','');if(!$secret||!$header)return false;$parts=[];foreach(explode(',',$header) as $p){[$k,$v]=array_pad(explode('=',$p,2),2,null);if($k&&$v)$parts[$k][]=$v;}$ts=(int)($parts['t'][0]??0);if(!$ts||abs(time()-$ts)>300)return false;$expected=hash_hmac('sha256',$ts.'.'.$raw,$secret);foreach($parts['v1']??[] as $sig)if(hash_equals($expected,$sig))return true;return false;
    }
    /** Handles the normalize webhook operation for the current WorkIntel workflow. */ public function normalizeWebhook(array $p):?array
    {
        $type=$p['type']??'';$o=$p['data']['object']??[];if($type==='checkout.session.completed')return ['event_id'=>$p['id']??sha1(json_encode($p)),'event_type'=>$type,'checkout_uuid'=>$o['client_reference_id']??($o['metadata']['checkout_uuid']??null),'status'=>'completed','provider_customer_id'=>$o['customer']??null,'provider_subscription_id'=>$o['subscription']??null,'provider_transaction_id'=>$o['payment_intent']??null];
        if($type==='invoice.payment_failed')return ['event_id'=>$p['id']??sha1(json_encode($p)),'event_type'=>$type,'status'=>'payment_failed','provider_subscription_id'=>$o['subscription']??null];return null;
    }
}
