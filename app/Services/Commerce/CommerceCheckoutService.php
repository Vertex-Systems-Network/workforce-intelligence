<?php
namespace App\Services\Commerce;

use App\Models\{BillingInvoice,CommerceCheckoutSession,CommerceCoupon,CommerceCouponRedemption,CommerceDunningAttempt,CommerceProviderConfig,CommerceTaxRule,CommerceWebhookEvent,SubscriptionPlan,User,Workspace,WorkspaceSubscription};
use App\Services\Billing\SubscriptionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Provides commerce checkout service behavior within the WorkIntel application. */ class CommerceCheckoutService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly CommerceProviderRegistry $providers,private readonly SubscriptionService $subscriptions){}

    /** Handles the catalog operation for the current WorkIntel workflow. */ public function catalog():array
    {
        if(!SubscriptionPlan::query()->exists())\App\Support\PlanCatalog::sync();
        return SubscriptionPlan::query()->with('entitlements')->where('is_active',true)->where('is_public',true)->orderBy('sort_order')->get()->map(fn($p)=>[
            'id'=>$p->id,'slug'=>$p->slug,'name'=>$p->name,'description'=>$p->description,'currency'=>$p->currency,
            'monthly_price_per_seat'=>(float)$p->monthly_price_per_seat,'annual_price_per_seat'=>(float)$p->annual_price_per_seat,'trial_days'=>$p->trial_days,'is_popular'=>(bool)$p->is_popular,
            'features'=>$p->entitlements->filter(fn($e)=>str_starts_with($e->key,'feature.'))->mapWithKeys(fn($e)=>[$e->key=>$e->resolvedValue()])->all(),
        ])->values()->all();
    }

    /** Handles the quote operation for the current WorkIntel workflow. */ public function quote(Workspace $workspace,SubscriptionPlan $plan,string $interval,?string $couponCode=null):array
    {
        if(!in_array($interval,['monthly','annual'],true))throw ValidationException::withMessages(['billing_interval'=>['Choose monthly or annual billing.']]);
        $seats=max(1,$workspace->members()->where('status','active')->count());
        $unit=$interval==='annual'?(float)$plan->annual_price_per_seat:(float)$plan->monthly_price_per_seat;
        $subtotal=round($unit*$seats,2);$coupon=$couponCode?$this->findCoupon($couponCode,$plan,$workspace):null;$discount=$coupon?$this->discount($coupon,$subtotal,$plan->currency):0.0;
        $taxable=max(0,$subtotal-$discount);$tax=$this->tax($workspace,$taxable);$total=round($taxable+$tax['amount'],2);
        return compact('seats','unit','subtotal','discount','total')+['currency'=>$plan->currency,'coupon'=>$coupon,'tax'=>$tax];
    }

    /** Creates and persists the requested resource. */ public function create(Workspace $workspace,User $user,SubscriptionPlan $plan,string $interval,?string $providerKey=null,?string $couponCode=null):CommerceCheckoutSession
    {
        abort_unless($plan->is_active&&$plan->is_public,422,'This plan is not available for checkout.');
        $quote=$this->quote($workspace,$plan,$interval,$couponCode);
        $provider=$plan->slug==='free'?'manual':($providerKey?:$this->defaultProviderKey());
        $config=$this->providerConfig($provider,$plan->slug==='free');
        $session=CommerceCheckoutSession::create([
            'uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'user_id'=>$user->id,'subscription_plan_id'=>$plan->id,'commerce_coupon_id'=>$quote['coupon']?->id,
            'billing_interval'=>$interval,'provider'=>$provider,'status'=>'pending','seat_quantity'=>$quote['seats'],'currency'=>$quote['currency'],'subtotal'=>$quote['subtotal'],'discount_total'=>$quote['discount'],'tax_total'=>$quote['tax']['amount'],'total'=>$quote['total'],'expires_at'=>now()->addHour(),
            'metadata'=>['tax_rule_id'=>$quote['tax']['rule_id'],'tax_rate'=>$quote['tax']['rate']],
        ]);
        $session->load('plan');
        if($plan->slug==='free'||(float)$session->total<=0){return $this->complete($session,'no-charge',null,null);}
        $result=$this->providers->get($provider)->createCheckout($config,$session,$user->email);
        $session->update(['status'=>$result['status']??'pending','provider_session_id'=>$result['provider_session_id']??null,'checkout_url'=>$result['checkout_url']??null,'metadata'=>array_merge($session->metadata??[],$result['metadata']??[])]);
        return $session->fresh(['plan','coupon']);
    }

    /** Handles the complete operation for the current WorkIntel workflow. */ public function complete(CommerceCheckoutSession $session,?string $providerTransactionId=null,?string $providerCustomerId=null,?string $providerSubscriptionId=null):CommerceCheckoutSession
    {
        return DB::transaction(function()use($session,$providerTransactionId,$providerCustomerId,$providerSubscriptionId){
            $locked=CommerceCheckoutSession::query()->lockForUpdate()->findOrFail($session->id);if($locked->status==='completed')return $locked->fresh(['plan','coupon']);$locked->load('workspace','plan','coupon');
            $subscription=$this->subscriptions->changePlan($locked->workspace,$locked->plan->slug,$locked->billing_interval,false);
            $subscription->update(['provider'=>$locked->provider,'provider_customer_id'=>$providerCustomerId?:$subscription->provider_customer_id,'provider_subscription_id'=>$providerSubscriptionId?:$subscription->provider_subscription_id,'seat_quantity'=>$locked->seat_quantity]);
            $invoice=BillingInvoice::query()->where('workspace_id',$locked->workspace_id)->where('workspace_subscription_id',$subscription->id)->latest('id')->first();
            if($invoice&&$locked->plan->slug!=='free'){
                $invoice->update(['provider'=>$locked->provider,'subtotal'=>$locked->subtotal,'discount_total'=>$locked->discount_total,'tax_total'=>$locked->tax_total,'total'=>$locked->total,'amount_due'=>$locked->total,'metadata'=>array_merge($invoice->metadata??[],['checkout_uuid'=>$locked->uuid,'coupon_code'=>$locked->coupon?->code])]);
                $this->subscriptions->markInvoicePaid($invoice,$providerTransactionId?:$locked->provider_session_id);
            }
            if($locked->coupon){$this->redeemCoupon($locked);}
            $locked->update(['status'=>'completed','completed_at'=>now(),'metadata'=>array_merge($locked->metadata??[],['provider_transaction_id'=>$providerTransactionId])]);
            return $locked->fresh(['plan','coupon']);
        });
    }

    /** Handles the process webhook operation for the current WorkIntel workflow. */ public function processWebhook(string $provider,Request $request):array
    {
        $config=$this->providerConfig($provider);$raw=$request->getContent();$adapter=$this->providers->get($provider);abort_unless($adapter->verifyWebhook($config,$request,$raw),401,'Invalid webhook signature.');
        $payload=json_decode($raw,true);abort_unless(is_array($payload),400,'Invalid webhook payload.');$event=$adapter->normalizeWebhook($payload);if(!$event)return ['ignored'=>true];$eventId=(string)$event['event_id'];
        try{$row=CommerceWebhookEvent::create(['provider'=>$provider,'event_id'=>$eventId,'event_type'=>$event['event_type']??null,'payload_hash'=>hash('sha256',$raw),'status'=>'processing','created_at'=>now()]);}catch(UniqueConstraintViolationException){return ['duplicate'=>true];}
        try{
            if(($event['status']??null)==='completed'&&!empty($event['checkout_uuid'])){$session=CommerceCheckoutSession::where('uuid',$event['checkout_uuid'])->firstOrFail();$this->complete($session,$event['provider_transaction_id']??null,$event['provider_customer_id']??null,$event['provider_subscription_id']??null);}
            elseif(($event['status']??null)==='payment_failed'){$this->markPaymentFailed($event['provider_subscription_id']??null,$event['event_type']??'provider_failure');}
            $row->update(['status'=>'processed','processed_at'=>now()]);return ['processed'=>true];
        }catch(\Throwable $e){$row->update(['status'=>'failed','error_message'=>mb_substr($e->getMessage(),0,2000)]);try{app(\App\Services\Observability\ObservabilityService::class)->record('payment','payment.webhook_failed','Commerce webhook processing failed.','critical',['provider'=>$provider,'event_type'=>$event['event_type']??null,'exception_class'=>$e::class],null,'commerce');}catch(\Throwable){}throw $e;}
    }

    /** Handles the settle manual operation for the current WorkIntel workflow. */ public function settleManual(CommerceCheckoutSession $session,string $reference):CommerceCheckoutSession
    {
        abort_unless(in_array($session->provider,['manual','bank_transfer'],true),422,'Only manual or bank-transfer checkout can be settled manually.');return $this->complete($session,$reference);
    }

    /** Handles the default provider key operation for the current WorkIntel workflow. */ private function defaultProviderKey():string
    {
        return CommerceProviderConfig::where('enabled',true)->where('is_default',true)->value('provider')?:CommerceProviderConfig::where('enabled',true)->value('provider')?:'manual';
    }
    /** Handles the provider config operation for the current WorkIntel workflow. */ private function providerConfig(string $key,bool $allowDisabled=false):CommerceProviderConfig
    {
        $config=CommerceProviderConfig::where('provider',$key)->first();if(!$config)throw new RuntimeException("Commerce provider {$key} is not configured.");if(!$allowDisabled&&!$config->enabled)throw ValidationException::withMessages(['provider'=>['Selected payment provider is disabled.']]);if(!$allowDisabled&&!in_array($key,['manual','bank_transfer'],true)&&$config->health_status!=='healthy')throw ValidationException::withMessages(['provider'=>['Selected payment provider is not healthy. Test it in Seller Console.']]);return $config;
    }
    /** Returns find coupon data required by the current workflow. */ private function findCoupon(string $code,SubscriptionPlan $plan,Workspace $workspace):CommerceCoupon
    {
        $coupon=CommerceCoupon::whereRaw('UPPER(code)=?',[strtoupper(trim($code))])->first();if(!$coupon||!$coupon->active)throw ValidationException::withMessages(['coupon_code'=>['Coupon is invalid.']]);
        if($coupon->starts_at?->isFuture()||$coupon->redeem_by?->isPast())throw ValidationException::withMessages(['coupon_code'=>['Coupon is not active.']]);if($coupon->max_redemptions!==null&&$coupon->redeemed_count>=$coupon->max_redemptions)throw ValidationException::withMessages(['coupon_code'=>['Coupon redemption limit has been reached.']]);if($coupon->eligible_plans&&!in_array($plan->slug,$coupon->eligible_plans,true))throw ValidationException::withMessages(['coupon_code'=>['Coupon does not apply to this plan.']]);if(CommerceCouponRedemption::where('commerce_coupon_id',$coupon->id)->where('workspace_id',$workspace->id)->exists())throw ValidationException::withMessages(['coupon_code'=>['This workspace has already redeemed this coupon.']]);return $coupon;
    }
    /** Handles the discount operation for the current WorkIntel workflow. */ private function discount(CommerceCoupon $coupon,float $subtotal,string $currency):float
    {
        if($coupon->discount_type==='percent')return round(min($subtotal,$subtotal*((float)$coupon->discount_value/100)),2);if($coupon->currency&&strtoupper($coupon->currency)!==strtoupper($currency))throw ValidationException::withMessages(['coupon_code'=>['Coupon currency does not match the plan.']]);return round(min($subtotal,(float)$coupon->discount_value),2);
    }
    /** Handles the tax operation for the current WorkIntel workflow. */ private function tax(Workspace $workspace,float $taxable):array
    {
        $country=strtoupper((string)$workspace->country);
        $state=trim((string)($workspace->preferences?->state_region ?? ''));
        $query=CommerceTaxRule::where('active',true)
            ->where(function($q)use($country){$q->whereNull('country')->orWhere('country',$country);})
            ->where(function($q)use($state){$q->whereNull('state_region');if($state!=='')$q->orWhere('state_region',$state);});
        $rule=$query
            ->orderByRaw('state_region is null')
            ->orderByRaw('country is null')
            ->orderBy('priority')
            ->first();
        $rate=(float)($rule?->rate_percent??0);
        return ['rule_id'=>$rule?->id,'rate'=>$rate,'amount'=>round($taxable*$rate/100,2)];
    }
    /** Handles the redeem coupon operation for the current WorkIntel workflow. */ private function redeemCoupon(CommerceCheckoutSession $session):void
    {
        $coupon=CommerceCoupon::lockForUpdate()->find($session->commerce_coupon_id);if(!$coupon)return;CommerceCouponRedemption::firstOrCreate(['commerce_coupon_id'=>$coupon->id,'workspace_id'=>$session->workspace_id],['commerce_checkout_session_id'=>$session->id,'discount_amount'=>$session->discount_total,'redeemed_at'=>now()]);$coupon->update(['redeemed_count'=>$coupon->redemptions()->count()]);
    }
    /** Handles the mark payment failed operation for the current WorkIntel workflow. */ private function markPaymentFailed(?string $providerSubscriptionId,string $reason):void
    {
        if(!$providerSubscriptionId)return;$sub=WorkspaceSubscription::where('provider_subscription_id',$providerSubscriptionId)->first();if(!$sub)return;$sub->update(['status'=>'past_due','grace_ends_at'=>now()->addDays((int)config('workintel.billing.grace_days',7))]);$next=(int)CommerceDunningAttempt::where('workspace_subscription_id',$sub->id)->max('attempt_number')+1;CommerceDunningAttempt::create(['workspace_subscription_id'=>$sub->id,'attempt_number'=>$next,'status'=>'scheduled','next_attempt_at'=>now()->addDays(min(7,$next*2)),'failure_message'=>$reason]);
    }
}
