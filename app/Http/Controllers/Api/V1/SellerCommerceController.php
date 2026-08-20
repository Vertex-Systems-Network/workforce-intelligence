<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;use App\Models\{BillingInvoice,BillingTransaction,CommerceCheckoutSession,CommerceCoupon,CommerceProviderConfig,CommerceRefund,CommerceTaxRule,PlatformAddon,SubscriptionPlan,Workspace,WorkspaceSubscription};use App\Services\Commerce\{CommerceCheckoutService,CommerceProviderRegistry,SellerAnalyticsService};use Illuminate\Http\JsonResponse;use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;use Illuminate\Support\Str;use Illuminate\Validation\Rule;use App\Support\PlanCatalog;
/** Provides seller commerce controller behavior within the WorkIntel application. */ class SellerCommerceController extends Controller
{
    /** Handles the overview operation for the current WorkIntel workflow. */ public function overview(SellerAnalyticsService $analytics,CommerceProviderRegistry $providers):JsonResponse{return response()->json(['summary'=>$analytics->summary(),'providers'=>CommerceProviderConfig::orderByDesc('is_default')->orderBy('provider')->get()->map(fn($p)=>$this->providerPayload($p)),'provider_catalog'=>$providers->catalog(),'plans'=>SubscriptionPlan::with(['entitlements'])->withCount('subscriptions')->orderBy('sort_order')->get(),'capability_catalog'=>PlanCatalog::capabilities(),'addons'=>PlatformAddon::withCount('subscriptions')->orderBy('name')->get(),'coupons'=>CommerceCoupon::latest()->limit(100)->get(),'tax_rules'=>CommerceTaxRule::orderBy('priority')->get(),'recent_checkouts'=>CommerceCheckoutSession::with(['workspace','plan'])->latest()->limit(30)->get(),'recent_refunds'=>CommerceRefund::latest()->limit(30)->get(),'recent_transactions'=>BillingTransaction::with('invoice')->latest()->limit(50)->get(),'dunning_attempts'=>\App\Models\CommerceDunningAttempt::with(['subscription.plan'])->latest()->limit(30)->get()]);}
    /** Handles the customers operation for the current WorkIntel workflow. */ public function customers(Request $r):JsonResponse{$q=Workspace::with(['subscription.plan'])->withCount(['members as active_members_count'=>fn($x)=>$x->where('status','active')])->where('workspace_type','production');if($s=trim((string)$r->query('q'))) $q->where(fn($x)=>$x->where('name','like','%'.$s.'%')->orWhere('slug','like','%'.$s.'%'));return response()->json(['data'=>$q->latest()->paginate(min(100,max(10,(int)$r->query('per_page',30))))]);}
    /** Handles the customer operation for the current WorkIntel workflow. */ public function customer(Workspace $workspace):JsonResponse{return response()->json(['workspace'=>$workspace->load(['subscription.plan','addons.addon']),'invoices'=>BillingInvoice::where('workspace_id',$workspace->id)->latest()->limit(50)->get(),'transactions'=>BillingTransaction::where('workspace_id',$workspace->id)->latest()->limit(50)->get(),'checkouts'=>CommerceCheckoutSession::where('workspace_id',$workspace->id)->latest()->limit(30)->get(),'refunds'=>CommerceRefund::where('workspace_id',$workspace->id)->latest()->limit(30)->get()]);}
    /** Saves a platform payment provider and activates remote providers only after their connection test succeeds. */
    public function saveProvider(Request $request,string $provider,CommerceProviderRegistry $registry):JsonResponse
    {
        abort_unless(in_array($provider,array_column($registry->catalog(),'key'),true),404);
        $data=$request->validate([
            'display_name'=>'required|string|max:100',
            'enabled'=>'required|boolean',
            'is_default'=>'required|boolean',
            'test_mode'=>'required|boolean',
            'credentials'=>'nullable|array',
            'settings'=>'nullable|array',
        ]);

        $config=CommerceProviderConfig::firstOrNew(['provider'=>$provider]);
        if(!$config->exists)$config->uuid=(string)Str::uuid();

        $remote=!in_array($provider,['manual','bank_transfer'],true);
        $requestedEnabled=(bool)$data['enabled'];
        $requestedDefault=(bool)$data['is_default'];
        $requiresActivationTest=$remote&&$requestedEnabled;
        $initiallyEnabled=$requiresActivationTest?false:$requestedEnabled;

        $config->fill([
            'display_name'=>$data['display_name'],
            'enabled'=>$initiallyEnabled,
            'is_default'=>$initiallyEnabled&&$requestedDefault,
            'test_mode'=>$data['test_mode'],
            'settings'=>$data['settings']??[],
            'updated_by'=>$request->user()->id,
        ]);
        if(array_key_exists('credentials',$data)&&$data['credentials']!==null){
            $config->credentials=array_merge($config->credentials??[],$data['credentials']);
        }

        DB::transaction(function()use($config,$requestedDefault,$initiallyEnabled){
            if($requestedDefault&&$initiallyEnabled){
                CommerceProviderConfig::where('provider','!=',$config->provider)->update(['is_default'=>false]);
            }
            $config->save();
        });

        $activationTest=null;
        if($requiresActivationTest){
            try{
                $activationTest=$registry->get($provider)->test($config->fresh());
            }catch(\Throwable $exception){
                report($exception);
                $activationTest=['ok'=>false,'message'=>Str::limit($exception->getMessage(),2000,'')];
            }

            DB::transaction(function()use($config,$requestedDefault,$activationTest){
                $enabled=(bool)($activationTest['ok']??false);
                if($enabled&&$requestedDefault){
                    CommerceProviderConfig::where('provider','!=',$config->provider)->update(['is_default'=>false]);
                }
                $config->update([
                    'last_tested_at'=>now(),
                    'health_status'=>$enabled?'healthy':'failed',
                    'health_message'=>$activationTest['message']??($enabled?'Connection test passed.':'Connection test failed.'),
                    'enabled'=>$enabled,
                    'is_default'=>$enabled&&$requestedDefault,
                ]);
            });
        }

        return response()->json(['data'=>$this->providerPayload($config->fresh()),'activation_test'=>$activationTest]);
    }
    /** Handles the test provider operation for the current WorkIntel workflow. */ public function testProvider(string $provider,CommerceProviderRegistry $registry):JsonResponse{$row=CommerceProviderConfig::where('provider',$provider)->firstOrFail();try{$result=$registry->get($provider)->test($row);$row->update(['last_tested_at'=>now(),'health_status'=>$result['ok']?'healthy':'failed','health_message'=>$result['message']]);return response()->json(['data'=>$this->providerPayload($row->fresh()),'result'=>$result],$result['ok']?200:422);}catch(\Throwable $e){$row->update(['last_tested_at'=>now(),'health_status'=>'failed','health_message'=>mb_substr($e->getMessage(),0,2000)]);return response()->json(['message'=>$e->getMessage(),'data'=>$this->providerPayload($row->fresh())],422);}}
    /** Handles the store coupon operation for the current WorkIntel workflow. */ public function storeCoupon(Request $r):JsonResponse{$d=$r->validate(['code'=>'required|string|max:64|unique:commerce_coupons,code','name'=>'required|string|max:120','discount_type'=>['required',Rule::in(['percent','fixed'])],'discount_value'=>'required|numeric|min:0.01','currency'=>'nullable|string|size:3','eligible_plans'=>'nullable|array','eligible_plans.*'=>'string|max:40','max_redemptions'=>'nullable|integer|min:1','starts_at'=>'nullable|date','redeem_by'=>'nullable|date|after:starts_at','active'=>'required|boolean']);$d['code']=strtoupper(trim($d['code']));$d['currency']=isset($d['currency'])?strtoupper($d['currency']):null;$coupon=CommerceCoupon::create(['uuid'=>(string)Str::uuid(),'created_by'=>$r->user()->id]+$d);return response()->json(['data'=>$coupon],201);}
    /** Updates update coupon data for the requested resource. */ public function updateCoupon(Request $r,CommerceCoupon $commerceCoupon):JsonResponse{$d=$r->validate(['name'=>'sometimes|string|max:120','active'=>'sometimes|boolean','max_redemptions'=>'nullable|integer|min:1','redeem_by'=>'nullable|date']);$commerceCoupon->update($d);return response()->json(['data'=>$commerceCoupon->fresh()]);}
    /** Handles the store tax operation for the current WorkIntel workflow. */ public function storeTax(Request $r):JsonResponse{$d=$r->validate(['name'=>'required|string|max:120','country'=>'nullable|string|size:2','state_region'=>'nullable|string|max:100','rate_percent'=>'required|numeric|min:0|max:100','active'=>'required|boolean','priority'=>'required|integer|min:0|max:1000']);$d['country']=isset($d['country'])?strtoupper($d['country']):null;$row=CommerceTaxRule::create(['uuid'=>(string)Str::uuid(),'created_by'=>$r->user()->id]+$d);return response()->json(['data'=>$row],201);}
    /** Updates update tax data for the requested resource. */ public function updateTax(Request $r,CommerceTaxRule $commerceTaxRule):JsonResponse{$d=$r->validate(['name'=>'sometimes|string|max:120','country'=>'nullable|string|size:2','state_region'=>'nullable|string|max:100','rate_percent'=>'sometimes|numeric|min:0|max:100','active'=>'sometimes|boolean','priority'=>'sometimes|integer|min:0|max:1000']);if(isset($d['country']))$d['country']=strtoupper($d['country']);$commerceTaxRule->update($d);return response()->json(['data'=>$commerceTaxRule->fresh()]);}
    /** Updates update addon data for the requested resource. */ public function updateAddon(Request $r,PlatformAddon $platformAddon):JsonResponse{$d=$r->validate(['name'=>'sometimes|string|max:140','description'=>'nullable|string|max:1000','status'=>['sometimes',Rule::in(['active','inactive'])],'monthly_price'=>'sometimes|numeric|min:0','unit_price'=>'sometimes|numeric|min:0','included_quantity'=>'sometimes|numeric|min:0']);$platformAddon->update($d);return response()->json(['data'=>$platformAddon->fresh()]);}
    /** Updates update plan data for the requested resource. */ public function updatePlan(Request $r,SubscriptionPlan $subscriptionPlan):JsonResponse{$d=$r->validate(['name'=>'sometimes|string|max:80','description'=>'nullable|string|max:1000','monthly_price_per_seat'=>'sometimes|numeric|min:0','annual_price_per_seat'=>'sometimes|numeric|min:0','trial_days'=>'sometimes|integer|min:0|max:365','is_active'=>'sometimes|boolean','is_public'=>'sometimes|boolean','is_popular'=>'sometimes|boolean','sort_order'=>'sometimes|integer|min:0|max:1000']);$subscriptionPlan->update($d);return response()->json(['data'=>$subscriptionPlan->fresh('entitlements')]);}

    /** Replaces seller-editable plan entitlement values without changing entitlement keys. */ public function updatePlanEntitlements(Request $r,SubscriptionPlan $subscriptionPlan):JsonResponse{$d=$r->validate(['entitlements'=>'required|array','entitlements.*'=>'nullable']);$catalog=collect(PlanCatalog::capabilities())->keyBy('key');foreach($d['entitlements'] as $key=>$value){$definition=$catalog->get($key);abort_unless($definition,422,'Unknown plan capability: '.$key);$resolved=match($definition['value_type']){'boolean'=>(bool)$value,'integer'=>(int)$value,default=>(string)$value};$subscriptionPlan->entitlements()->updateOrCreate(['key'=>$key],['value_type'=>$definition['value_type'],'value'=>['value'=>$resolved],'label'=>$definition['label']]);}return response()->json(['data'=>$subscriptionPlan->fresh('entitlements')]);}
    /** Handles the settle checkout operation for the current WorkIntel workflow. */ public function settleCheckout(Request $r,CommerceCheckoutSession $commerceCheckoutSession,CommerceCheckoutService $commerce):JsonResponse{$d=$r->validate(['reference'=>'required|string|max:190']);return response()->json(['data'=>$commerce->settleManual($commerceCheckoutSession,$d['reference'])]);}
    /** Handles the refund operation for the current WorkIntel workflow. */ public function refund(Request $r,BillingTransaction $billingTransaction,CommerceProviderRegistry $registry):JsonResponse{$d=$r->validate(['amount'=>'required|numeric|min:0.01','reason'=>'nullable|string|max:500']);abort_unless($billingTransaction->status==='succeeded'&&$billingTransaction->type==='payment',422,'Only succeeded payments can be refunded.');$amount=(float)$d['amount'];abort_if($amount>(float)$billingTransaction->amount,422,'Refund cannot exceed original payment.');$row=CommerceRefund::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$billingTransaction->workspace_id,'billing_invoice_id'=>$billingTransaction->billing_invoice_id,'billing_transaction_id'=>$billingTransaction->id,'provider'=>$billingTransaction->provider,'status'=>'pending','currency'=>$billingTransaction->currency,'amount'=>$amount,'reason'=>$d['reason']??null,'requested_by'=>$r->user()->id]);try{$cfg=CommerceProviderConfig::where('provider',$row->provider)->firstOrFail();$result=$registry->get($row->provider)->refund($cfg,$row,$billingTransaction);$row->update(['status'=>$result['status'],'provider_refund_id'=>$result['provider_refund_id']??null,'processed_at'=>in_array($result['status'],['succeeded','failed'],true)?now():null]);if($row->status==='succeeded')BillingTransaction::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$row->workspace_id,'billing_invoice_id'=>$row->billing_invoice_id,'provider'=>$row->provider,'type'=>'refund','status'=>'succeeded','currency'=>$row->currency,'amount'=>-$amount,'provider_transaction_id'=>$row->provider_refund_id,'processed_at'=>now(),'metadata'=>['refund_uuid'=>$row->uuid]]);return response()->json(['data'=>$row->fresh()]);}catch(\Throwable $e){$row->update(['status'=>'failed','failure_message'=>mb_substr($e->getMessage(),0,2000),'processed_at'=>now()]);return response()->json(['message'=>$e->getMessage(),'data'=>$row->fresh()],422);}}
    /** Handles the provider payload operation for the current WorkIntel workflow. */ private function providerPayload(CommerceProviderConfig $p):array{return ['id'=>$p->id,'uuid'=>$p->uuid,'provider'=>$p->provider,'display_name'=>$p->display_name,'enabled'=>$p->enabled,'is_default'=>$p->is_default,'test_mode'=>$p->test_mode,'settings'=>$p->settings,'has_credentials'=>!empty($p->credentials),'last_tested_at'=>$p->last_tested_at?->toIso8601String(),'health_status'=>$p->health_status,'health_message'=>$p->health_message];}
}
