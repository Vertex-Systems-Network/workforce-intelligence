<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Models\ClientPaymentCheckoutSession;
use App\Models\WorkspaceClientPaymentGateway;
use App\Services\ClientPortal\ClientPaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Exposes safe client-portal payment options and checkout lifecycle endpoints. */
class ClientPortalPaymentController extends Controller
{
    /** Returns enabled gateways that are allowed for the requested invoice. */
    public function options(Request $request,ClientInvoice $clientInvoice,ClientPaymentGatewayService $service):JsonResponse
    {
        $client=$request->attributes->get('client');abort_unless($clientInvoice->client_id===$client->id,404);abort_if($clientInvoice->status==='draft',404);$allowed=$clientInvoice->allowed_gateways??[];
        $rows=WorkspaceClientPaymentGateway::query()->where('workspace_id',$clientInvoice->workspace_id)->where('enabled',true)->where('client_portal_enabled',true)->when($allowed,fn($q)=>$q->whereIn('provider',$allowed))->orderByDesc('is_default')->orderBy('sort_order')->get();
        return response()->json(['invoice'=>['id'=>$clientInvoice->id,'number'=>$clientInvoice->number,'status'=>$clientInvoice->status,'currency'=>$clientInvoice->currency,'amount_due'=>(float)$clientInvoice->amount_due],'gateways'=>$rows->map(fn($g)=>['id'=>$g->id,'provider'=>$g->provider,'display_name'=>$g->display_name,'is_default'=>$g->is_default,'hosted'=>(bool)collect($service->catalog())->firstWhere('key',$g->provider)['hosted'],'instructions'=>in_array($g->provider,['manual','bank_transfer'],true)?($g->settings['instructions']??null):null])->values()]);
    }

    /** Creates a payment checkout scoped to the authenticated client portal account. */
    public function checkout(Request $request,ClientInvoice $clientInvoice,ClientPaymentGatewayService $service):JsonResponse
    {
        $client=$request->attributes->get('client');abort_unless($clientInvoice->client_id===$client->id,404);$data=$request->validate(['gateway_id'=>'required|integer']);$gateway=WorkspaceClientPaymentGateway::where('workspace_id',$clientInvoice->workspace_id)->findOrFail($data['gateway_id']);$session=$service->createCheckout($clientInvoice,$gateway);return response()->json(['data'=>$this->payload($session)],201);
    }

    /** Returns and reconciles a client-owned checkout without exposing gateway secrets. */
    public function show(Request $request,ClientPaymentCheckoutSession $checkout,ClientPaymentGatewayService $service):JsonResponse
    {
        $client=$request->attributes->get('client');abort_unless($checkout->client_id===$client->id,404);return response()->json(['data'=>$this->payload($service->reconcile($checkout))]);
    }

    /** Shapes a checkout for client portal use. */
    private function payload(ClientPaymentCheckoutSession $session):array
    {
        $session->loadMissing('invoice:id,number');return ['id'=>$session->id,'uuid'=>$session->uuid,'invoice_id'=>$session->client_invoice_id,'invoice_number'=>$session->invoice?->number,'provider'=>$session->provider,'status'=>$session->status,'currency'=>$session->currency,'amount'=>(float)$session->amount,'checkout_url'=>$session->checkout_url,'expires_at'=>$session->expires_at?->toIso8601String(),'completed_at'=>$session->completed_at?->toIso8601String(),'instructions'=>$session->metadata['instructions']??null,'bank_details'=>$session->metadata['bank_details']??null];
    }
}
