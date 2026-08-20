<?php
namespace App\Services\ClientPortal;

use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\ClientPaymentCheckoutSession;
use App\Models\WorkspaceClientPaymentGateway;
use App\Services\Commerce\CommerceUrlGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Orchestrates workspace-owned client payment gateways and reconciles hosted checkouts. */
class ClientPaymentGatewayService
{
    /** Returns providers that a workspace may configure for client invoice payments. */
    public function catalog(): array
    {
        return [
            ['key'=>'manual','name'=>'Manual Payment','hosted'=>false],
            ['key'=>'bank_transfer','name'=>'Bank Transfer','hosted'=>false],
            ['key'=>'stripe','name'=>'Stripe','hosted'=>true],
            ['key'=>'paypal','name'=>'PayPal','hosted'=>true],
            ['key'=>'custom_http','name'=>'Custom Hosted Checkout','hosted'=>true],
        ];
    }

    /** Tests gateway credentials without returning secrets to callers. */
    public function test(WorkspaceClientPaymentGateway $gateway): array
    {
        $credentials=$gateway->credentials??[];$settings=$gateway->settings??[];
        if(in_array($gateway->provider,['manual','bank_transfer'],true)) return ['ok'=>true,'message'=>'No remote connection is required for this gateway.'];
        if($gateway->provider==='stripe'){
            $secret=trim((string)($credentials['secret_key']??''));
            if($secret==='') return ['ok'=>false,'message'=>'Stripe secret key is required.'];
            if(!preg_match('/^sk_(?:test|live)_[A-Za-z0-9]+$/',$secret)||preg_match('/(?:invalid|example|changeme|replace)/i',$secret)) return ['ok'=>false,'message'=>'Stripe secret key format is invalid or uses a placeholder value.'];
            $response=Http::withToken($secret)->acceptJson()->timeout(12)->get('https://api.stripe.com/v1/account');
            return ['ok'=>$response->successful(),'message'=>$response->successful()?'Stripe account verified.':'Stripe verification failed (HTTP '.$response->status().').'];
        }
        if($gateway->provider==='paypal'){
            $token=$this->paypalToken($gateway);
            return ['ok'=>$token!=='','message'=>$token!==''?'PayPal credentials verified.':'PayPal credential verification failed.'];
        }
        if($gateway->provider==='custom_http'){
            $url=trim((string)($settings['test_url']??$settings['checkout_url']??''));
            if($url==='') return ['ok'=>false,'message'=>'A custom checkout or test URL is required.'];
            app(CommerceUrlGuard::class)->assertPublicHttps($url);
            $response=Http::acceptJson()->timeout(12)->get($url);
            return ['ok'=>$response->successful(),'message'=>$response->successful()?'Custom checkout endpoint is reachable.':'Custom endpoint returned HTTP '.$response->status().'.'];
        }
        return ['ok'=>false,'message'=>'Unsupported client payment gateway.'];
    }

    /** Creates one client-facing payment checkout for the invoice's outstanding balance. */
    public function createCheckout(ClientInvoice $invoice, WorkspaceClientPaymentGateway $gateway): ClientPaymentCheckoutSession
    {
        abort_unless($invoice->workspace_id===$gateway->workspace_id,404);
        abort_unless($gateway->enabled&&$gateway->client_portal_enabled,422,'This payment gateway is not enabled for the client portal.');
        abort_if(in_array($invoice->status,['draft','void','paid'],true)||((float)$invoice->amount_due)<=0,422,'This invoice cannot accept a payment.');
        $allowed=$invoice->allowed_gateways??[];
        abort_if($allowed&&!in_array($gateway->provider,$allowed,true),422,'This gateway is not allowed for the invoice.');
        if(!in_array($gateway->provider,['manual','bank_transfer'],true)) abort_unless($gateway->health_status==='healthy',422,'Test this gateway successfully before accepting client payments.');

        return DB::transaction(function()use($invoice,$gateway){
            $session=ClientPaymentCheckoutSession::create([
                'uuid'=>(string)Str::uuid(),'workspace_id'=>$invoice->workspace_id,'client_id'=>$invoice->client_id,'client_invoice_id'=>$invoice->id,
                'workspace_client_payment_gateway_id'=>$gateway->id,'provider'=>$gateway->provider,'status'=>'pending','currency'=>$invoice->currency,
                'amount'=>$invoice->amount_due,'expires_at'=>now()->addMinutes(60),'metadata'=>[],
            ]);
            $result=$this->startProviderCheckout($session,$gateway,$invoice);
            $session->update([
                'status'=>$result['status']??'pending','checkout_url'=>$result['checkout_url']??null,'provider_session_id'=>$result['provider_session_id']??null,
                'metadata'=>array_merge($session->metadata??[],$result['metadata']??[]),
            ]);
            return $session->fresh('gateway');
        });
    }

    /** Reconciles a hosted checkout with the provider and records payment exactly once. */
    public function reconcile(ClientPaymentCheckoutSession $session): ClientPaymentCheckoutSession
    {
        if(in_array($session->status,['completed','failed','expired'],true)) return $session;
        $gateway=$session->gateway;
        if(!$gateway)return $session;
        if($session->expires_at?->isPast()){$session->update(['status'=>'expired']);return $session->fresh();}
        if(in_array($session->provider,['manual','bank_transfer'],true))return $session;
        try{$result=$this->providerStatus($session,$gateway);}catch(\Throwable $e){$session->update(['failure_message'=>Str::limit($e->getMessage(),2000,'')]);return $session->fresh();}
        if(($result['status']??'pending')==='completed')$this->complete($session,$result['transaction_id']??$session->provider_session_id,(array)($result['metadata']??[]));
        elseif(($result['status']??'pending')==='failed')$session->update(['status'=>'failed','failed_at'=>now(),'failure_message'=>$result['message']??'Payment failed.']);
        return $session->fresh(['invoice.payments','gateway']);
    }

    /** Settles a manual or bank-transfer checkout after workspace-side verification. */
    public function settleManual(ClientPaymentCheckoutSession $session, string $reference, ?int $userId): ClientPaymentCheckoutSession
    {
        abort_unless(in_array($session->provider,['manual','bank_transfer'],true),422,'Only manual or bank-transfer checkouts can be settled manually.');
        abort_unless($session->status==='pending',422,'Checkout is not pending.');
        $this->complete($session,$reference,['settled_manually'=>true,'settled_by'=>$userId],$userId);
        return $session->fresh(['invoice.payments','gateway']);
    }

    /** Processes a bounded batch of pending hosted checkouts for scheduled reconciliation. */
    public function reconcilePending(int $limit=100): int
    {
        $count=0;
        ClientPaymentCheckoutSession::query()->where('status','pending')->whereNotIn('provider',['manual','bank_transfer'])->where('created_at','>=',now()->subDays(2))->orderBy('id')->limit(max(1,min(500,$limit)))->get()->each(function($session)use(&$count){$before=$session->status;$after=$this->reconcile($session)->status;if($before!==$after)$count++;});
        return $count;
    }

    /** Starts a provider-specific checkout and returns normalized checkout metadata. */
    private function startProviderCheckout(ClientPaymentCheckoutSession $session,WorkspaceClientPaymentGateway $gateway,ClientInvoice $invoice):array
    {
        $invoice->loadMissing('workspace');$base=rtrim(config('app.url'),'/');$portal=$base.'/portal/'.rawurlencode((string)$invoice->workspace->slug);$return=$portal.'?payment_checkout='.$session->id;$cancel=$portal.'?payment_cancelled=1';
        $credentials=$gateway->credentials??[];$settings=$gateway->settings??[];
        if($gateway->provider==='manual')return ['status'=>'pending','metadata'=>['instructions'=>$settings['instructions']??'Contact the billing team to arrange payment.']];
        if($gateway->provider==='bank_transfer')return ['status'=>'pending','metadata'=>['instructions'=>$settings['instructions']??'Use the bank details supplied by the billing team and include the invoice number as reference.','bank_details'=>$settings['bank_details']??null]];
        if($gateway->provider==='stripe'){
            $secret=trim((string)($credentials['secret_key']??''));if($secret==='')throw ValidationException::withMessages(['gateway'=>['Stripe secret key is missing.']]);
            $minor=(int)round(((float)$session->amount)*100);
            $response=Http::withToken($secret)->asForm()->timeout(15)->post('https://api.stripe.com/v1/checkout/sessions',[
                'mode'=>'payment','success_url'=>$return,'cancel_url'=>$cancel,'client_reference_id'=>$session->uuid,
                'line_items[0][price_data][currency]'=>strtolower($session->currency),'line_items[0][price_data][product_data][name]'=>'Invoice '.$invoice->number,
                'line_items[0][price_data][unit_amount]'=>$minor,'line_items[0][quantity]'=>1,'metadata[workintel_client_checkout]'=>$session->uuid,
            ]);
            if(!$response->successful())throw ValidationException::withMessages(['gateway'=>['Stripe checkout creation failed.']]);$payload=$response->json();
            return ['status'=>'pending','checkout_url'=>$payload['url']??null,'provider_session_id'=>$payload['id']??null];
        }
        if($gateway->provider==='paypal'){
            $token=$this->paypalToken($gateway);if($token==='')throw ValidationException::withMessages(['gateway'=>['PayPal credentials are invalid.']]);
            $baseUrl=$gateway->test_mode?'https://api-m.sandbox.paypal.com':'https://api-m.paypal.com';
            $response=Http::withToken($token)->acceptJson()->timeout(15)->post($baseUrl.'/v2/checkout/orders',[
                'intent'=>'CAPTURE','purchase_units'=>[['reference_id'=>$session->uuid,'invoice_id'=>$invoice->number,'amount'=>['currency_code'=>$session->currency,'value'=>number_format((float)$session->amount,2,'.','')]]],
                'payment_source'=>['paypal'=>['experience_context'=>['return_url'=>$return,'cancel_url'=>$cancel,'user_action'=>'PAY_NOW']]],
            ]);
            if(!$response->successful())throw ValidationException::withMessages(['gateway'=>['PayPal checkout creation failed.']]);$payload=$response->json();$approve=collect($payload['links']??[])->firstWhere('rel','payer-action')??collect($payload['links']??[])->firstWhere('rel','approve');
            return ['status'=>'pending','checkout_url'=>$approve['href']??null,'provider_session_id'=>$payload['id']??null];
        }
        if($gateway->provider==='custom_http'){
            $url=trim((string)($settings['checkout_url']??''));if($url==='')throw ValidationException::withMessages(['gateway'=>['Custom checkout URL is missing.']]);app(CommerceUrlGuard::class)->assertPublicHttps($url);
            $headers=is_array($credentials['headers']??null)?$credentials['headers']:[];
            $response=Http::withHeaders($headers)->acceptJson()->timeout(15)->post($url,['checkout_uuid'=>$session->uuid,'invoice_uuid'=>$invoice->uuid,'invoice_number'=>$invoice->number,'amount'=>(float)$session->amount,'currency'=>$session->currency,'return_url'=>$return,'cancel_url'=>$cancel]);
            if(!$response->successful())throw ValidationException::withMessages(['gateway'=>['Custom checkout endpoint failed.']]);$payload=$response->json();
            $checkoutUrl=trim((string)($payload['checkout_url']??''));if($checkoutUrl!=='')app(CommerceUrlGuard::class)->assertPublicHttps($checkoutUrl);return ['status'=>'pending','checkout_url'=>$checkoutUrl!==''?$checkoutUrl:null,'provider_session_id'=>$payload['session_id']??null];
        }
        throw ValidationException::withMessages(['gateway'=>['Unsupported gateway.']]);
    }

    /** Returns a normalized status from the configured hosted provider. */
    private function providerStatus(ClientPaymentCheckoutSession $session,WorkspaceClientPaymentGateway $gateway):array
    {
        $credentials=$gateway->credentials??[];$settings=$gateway->settings??[];
        if($gateway->provider==='stripe'){
            $response=Http::withToken((string)($credentials['secret_key']??''))->acceptJson()->timeout(12)->get('https://api.stripe.com/v1/checkout/sessions/'.$session->provider_session_id);
            if(!$response->successful())return ['status'=>'pending'];$p=$response->json();
            return ['status'=>(($p['payment_status']??'')==='paid'?'completed':(($p['status']??'')==='expired'?'failed':'pending')),'transaction_id'=>$p['payment_intent']??$p['id']??null,'metadata'=>$p];
        }
        if($gateway->provider==='paypal'){
            $token=$this->paypalToken($gateway);if($token==='')return ['status'=>'pending'];$base=$gateway->test_mode?'https://api-m.sandbox.paypal.com':'https://api-m.paypal.com';
            $response=Http::withToken($token)->acceptJson()->timeout(12)->get($base.'/v2/checkout/orders/'.$session->provider_session_id);if(!$response->successful())return ['status'=>'pending'];$p=$response->json();
            if(($p['status']??'')==='APPROVED'){$capture=Http::withToken($token)->acceptJson()->timeout(12)->post($base.'/v2/checkout/orders/'.$session->provider_session_id.'/capture');if($capture->successful())$p=$capture->json();}
            $captureId=$p['purchase_units'][0]['payments']['captures'][0]['id']??null;return ['status'=>(($p['status']??'')==='COMPLETED'?'completed':'pending'),'transaction_id'=>$captureId??$session->provider_session_id,'metadata'=>$p];
        }
        if($gateway->provider==='custom_http'){
            $url=trim((string)($settings['status_url']??''));if($url==='')return ['status'=>'pending'];app(CommerceUrlGuard::class)->assertPublicHttps($url);$headers=is_array($credentials['headers']??null)?$credentials['headers']:[];
            $response=Http::withHeaders($headers)->acceptJson()->timeout(12)->get($url,['session_id'=>$session->provider_session_id,'checkout_uuid'=>$session->uuid]);if(!$response->successful())return ['status'=>'pending'];$p=$response->json();
            return ['status'=>in_array($p['status']??'', ['completed','paid','succeeded'],true)?'completed':(($p['status']??'')==='failed'?'failed':'pending'),'transaction_id'=>$p['transaction_id']??null,'metadata'=>$p];
        }
        return ['status'=>'pending'];
    }

    /** Completes a checkout and records the invoice payment idempotently. */
    private function complete(ClientPaymentCheckoutSession $session,?string $transactionId,array $metadata=[],?int $userId=null):void
    {
        DB::transaction(function()use($session,$transactionId,$metadata,$userId){
            $locked=ClientPaymentCheckoutSession::query()->lockForUpdate()->findOrFail($session->id);if($locked->status==='completed')return;
            $invoice=ClientInvoice::query()->lockForUpdate()->findOrFail($locked->client_invoice_id);
            $existing=$transactionId?ClientPayment::query()->where('workspace_id',$locked->workspace_id)->where('provider',$locked->provider)->where('provider_transaction_id',$transactionId)->first():null;
            if($existing&&$existing->client_invoice_id!==$invoice->id)throw ValidationException::withMessages(['reference'=>['This provider transaction is already attached to another invoice.']]);
            if(!$existing){
                app(ClientInvoiceService::class)->recordPayment($invoice,['amount'=>min((float)$locked->amount,(float)$invoice->amount_due),'currency'=>$locked->currency,'method'=>$locked->provider,'provider'=>$locked->provider,'reference'=>$transactionId,'provider_transaction_id'=>$transactionId,'paid_on'=>now()->toDateString(),'note'=>'Client portal payment','metadata'=>['checkout_uuid'=>$locked->uuid]+$metadata],$userId);
            }
            $locked->update(['status'=>'completed','provider_transaction_id'=>$transactionId,'completed_at'=>now(),'metadata'=>array_merge($locked->metadata??[],$metadata)]);
        });
    }

    /** Creates a PayPal OAuth token for the workspace gateway. */
    private function paypalToken(WorkspaceClientPaymentGateway $gateway):string
    {
        $c=$gateway->credentials??[];$id=trim((string)($c['client_id']??''));$secret=trim((string)($c['client_secret']??''));if($id===''||$secret==='')return '';
        $base=$gateway->test_mode?'https://api-m.sandbox.paypal.com':'https://api-m.paypal.com';$response=Http::withBasicAuth($id,$secret)->asForm()->timeout(12)->post($base.'/v1/oauth2/token',['grant_type'=>'client_credentials']);return $response->successful()?(string)($response->json('access_token')??''):'';
    }
}
