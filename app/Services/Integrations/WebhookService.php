<?php
namespace App\Services\Integrations;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\Workspace;
use App\Services\Billing\EntitlementService;
use App\Services\Automation\AutomationEngine;
use App\Services\Security\OutboundUrlGuard;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Provides webhook service behavior within the WorkIntel application. */ class WebhookService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly AutomationEngine $automations) {}
    public const EVENTS=['time.started','time.paused','time.resumed','time.stopped','attendance.clocked_in','attendance.clocked_out','payroll.approved','payroll.paid','report.generated','documents.generated','documents.review_requested','documents.approved','documents.shared','documents.signed','website.page_published','website.lead_received','device.revoked','client_invoice.sent','client_invoice.payment_recorded','tasks.created','tasks.updated','tasks.deleted','projects.created','projects.updated','projects.deleted','leave.created','leave.updated','approvals.created','approvals.updated','expense_claims.created','expense_claims.updated','purchase_requests.created','purchase_requests.updated','work_orders.created','work_orders.updated','field.checkpoint_visited','incidents.created','incidents.updated','security_events.created','intelligence.insight_created','intelligence.insight_resolved','intelligence.run_completed','workspace.activity'];

    /** Handles the queue event operation for the current WorkIntel workflow. */ public function queueEvent(Workspace $workspace,string $event,array $data): void
    {
        try { $this->automations->emit($workspace,$event,$data,'workspace'); } catch (\Throwable $e) { report($e); }
        if (! Schema::hasTable('webhook_endpoints') || ! Schema::hasTable('webhook_deliveries')) return;
        if(!app(EntitlementService::class)->allows($workspace,'feature.api_access')) return;
        $eventId=(string)Str::uuid();
        foreach(WebhookEndpoint::where('workspace_id',$workspace->id)->where('status','active')->get() as $endpoint){
            if(!$endpoint->accepts($event)&&!$endpoint->accepts('*')) continue;
            WebhookDelivery::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'webhook_endpoint_id'=>$endpoint->id,'event_type'=>$event,'event_id'=>$eventId,'payload'=>['id'=>$eventId,'type'=>$event,'workspace_id'=>$workspace->id,'created_at'=>now()->toIso8601String(),'data'=>$data],'status'=>'pending','next_attempt_at'=>now(),'created_at'=>now()]);
        }
    }

    /** Handles the deliver operation for the current WorkIntel workflow. */ public function deliver(WebhookDelivery $delivery): bool
    {
        $delivery->loadMissing('endpoint');$endpoint=$delivery->endpoint;
        if(!$endpoint||$endpoint->status!=='active'){$delivery->update(['status'=>'failed','failed_at'=>now()]);return false;}
        try{
            app(OutboundUrlGuard::class)->assertSafe($endpoint->url);
            $body=json_encode($delivery->payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'{}';
            $timestamp=(string)now()->timestamp;$secret=Crypt::decryptString($endpoint->secret_encrypted);$signature=hash_hmac('sha256',$timestamp.'.'.$body,$secret);
            $response=Http::timeout(config('workintel_security.webhooks.timeout_seconds',10))->withHeaders(['Content-Type'=>'application/json','User-Agent'=>'WorkIntel-Webhooks/1.0','X-WorkIntel-Event'=>$delivery->event_type,'X-WorkIntel-Delivery'=>$delivery->uuid,'X-WorkIntel-Timestamp'=>$timestamp,'X-WorkIntel-Signature'=>'v1='.$signature])->withBody($body,'application/json')->post($endpoint->url);
            $delivery->attempts++;
            $delivery->last_status_code=$response->status();$delivery->last_response_excerpt=Str::limit($response->body(),config('workintel_security.webhooks.max_response_excerpt',900),'');
            if($response->successful()){$delivery->status='delivered';$delivery->delivered_at=now();$delivery->next_attempt_at=null;$endpoint->update(['last_success_at'=>now()]);$delivery->save();return true;}
            $this->retryOrFail($delivery);$endpoint->update(['last_failure_at'=>now()]);return false;
        }catch(\Throwable $e){$delivery->attempts++;$delivery->last_response_excerpt=Str::limit($e->getMessage(),900,'');$this->retryOrFail($delivery);$endpoint->update(['last_failure_at'=>now()]);return false;}
    }

    /** Handles the retry or fail operation for the current WorkIntel workflow. */ private function retryOrFail(WebhookDelivery $delivery): void
    {
        $max=$delivery->endpoint?->max_attempts??5;
        if($delivery->attempts >= $max){$delivery->status='failed';$delivery->failed_at=now();$delivery->next_attempt_at=null;}
        else{$delivery->status='retrying';$delivery->next_attempt_at=now()->addMinutes(min(60,2 ** $delivery->attempts));}
        $delivery->save();
        if($delivery->status==='failed'){
            try{app(\App\Services\Observability\ObservabilityService::class)->record('webhook','webhook.delivery_failed','Webhook delivery exhausted retries.','error',['delivery_uuid'=>$delivery->uuid,'event_type'=>$delivery->event_type,'endpoint_id'=>$delivery->webhook_endpoint_id,'status_code'=>$delivery->last_status_code],$delivery->workspace_id,'webhook');}catch(\Throwable){}
        }
    }
}
