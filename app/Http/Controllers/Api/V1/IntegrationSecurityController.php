<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\IntegrationConnection;
use App\Models\SecurityEvent;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WorkspaceApiKey;
use App\Services\Integrations\WebhookService;
use App\Services\Automation\ConnectorRegistry;
use App\Services\Security\OutboundUrlGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
/** Provides integration security controller behavior within the WorkIntel application. */ class IntegrationSecurityController extends Controller
{
    /** Handles the overview operation for the current WorkIntel workflow. */ public function overview(Request $request, ConnectorRegistry $connectors): JsonResponse
    {
        $w=$request->attributes->get('workspace');
        $schemaReady = Schema::hasTable('integration_connections') && Schema::hasTable('workspace_api_keys') && Schema::hasTable('webhook_endpoints') && Schema::hasTable('webhook_deliveries');
        return response()->json([
            'integrations'=>$schemaReady?IntegrationConnection::where('workspace_id',$w->id)->latest()->get()->map(fn($x)=>$this->integrationPayload($x)):[],
            'api_keys'=>$schemaReady?WorkspaceApiKey::where('workspace_id',$w->id)->latest('created_at')->get()->map(fn($x)=>$this->apiKeyPayload($x)):[],
            'webhooks'=>$schemaReady?WebhookEndpoint::where('workspace_id',$w->id)->withCount('deliveries')->latest()->get()->map(fn($x)=>$this->webhookPayload($x)):[],
            'schema_ready'=>$schemaReady,
            'event_catalog'=>WebhookService::EVENTS,
            'api_scope_catalog'=>['people.read','projects.read','tasks.read','time.read','time.write','attendance.read','reports.read'],
            'providers'=>$connectors->catalog(),
        ]);
    }
    /** Handles the store integration operation for the current WorkIntel workflow. */ public function storeIntegration(Request $request,ConnectorRegistry $connectors): JsonResponse
    {
        $w=$request->attributes->get('workspace');$data=$request->validate(['provider'=>['required',Rule::in($connectors->providerIds())],'name'=>['required','string','max:120'],'config'=>['required','array','max:24']]);$config=$connectors->validateConfig($data['provider'],$data['config']);
        $x=IntegrationConnection::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$w->id,'created_by'=>$request->user()->id,'provider'=>$data['provider'],'name'=>$data['name'],'status'=>'active','config_encrypted'=>Crypt::encryptString(json_encode($config,JSON_UNESCAPED_SLASHES))]);
        return response()->json(['data'=>$this->integrationPayload($x)],201);
    }
    /** Updates update integration data for the requested resource. */ public function updateIntegration(Request $request,IntegrationConnection $integration,ConnectorRegistry $connectors): JsonResponse
    {
        $w=$request->attributes->get('workspace');abort_unless($integration->workspace_id===$w->id,404);$data=$request->validate(['name'=>['sometimes','string','max:120'],'status'=>['sometimes',Rule::in(['active','paused'])],'config'=>['sometimes','array','max:24']]);
        if(isset($data['config']))$data['config_encrypted']=Crypt::encryptString(json_encode($connectors->validateConfig($integration->provider,$data['config']),JSON_UNESCAPED_SLASHES));unset($data['config']);$integration->update($data);return response()->json(['data'=>$this->integrationPayload($integration->fresh())]);
    }
    /** Handles the destroy integration operation for the current WorkIntel workflow. */ public function destroyIntegration(Request $request,IntegrationConnection $integration): JsonResponse { $w=$request->attributes->get('workspace');abort_unless($integration->workspace_id===$w->id,404);$integration->delete();return response()->json(null,204); }
    /** Handles the test integration operation for the current WorkIntel workflow. */ public function testIntegration(Request $request,IntegrationConnection $integration,ConnectorRegistry $connectors): JsonResponse
    {
        $w=$request->attributes->get('workspace');abort_unless($integration->workspace_id===$w->id,404);$config=json_decode(Crypt::decryptString($integration->config_encrypted),true)?:[];
        try{$result=$connectors->test($integration->provider,$config,8);$integration->update(['last_tested_at'=>now(),'last_error'=>null]);return response()->json(['message'=>'Connection test passed.','result'=>$result]);}catch(\Throwable $e){$integration->update(['last_tested_at'=>now(),'last_error'=>Str::limit($e->getMessage(),1000,'')]);return response()->json(['message'=>'Connection test failed.','error'=>$e->getMessage()],422);}
    }
    /** Handles the store api key operation for the current WorkIntel workflow. */ public function storeApiKey(Request $request): JsonResponse
    {
        $w=$request->attributes->get('workspace');$data=$request->validate(['name'=>['required','string','max:120'],'scopes'=>['required','array','min:1','max:20'],'scopes.*'=>['string',Rule::in(['*','people.read','projects.read','tasks.read','time.read','time.write','attendance.read','reports.read'])],'expires_days'=>['nullable','integer','min:1','max:3650']]);$plain='wiax_'.Str::random(64);
        $key=WorkspaceApiKey::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$w->id,'created_by'=>$request->user()->id,'name'=>$data['name'],'prefix'=>substr($plain,0,13),'token_hash'=>hash('sha256',$plain),'scopes'=>array_values(array_unique($data['scopes'])),'expires_at'=>now()->addDays($data['expires_days']??config('workintel_security.api.token_days',365)),'created_at'=>now()]);
        return response()->json(['data'=>$this->apiKeyPayload($key),'token'=>$plain],201);
    }
    /** Rotate an API key by issuing a successor and revoking the old credential atomically. */ public function rotateApiKey(Request $request,WorkspaceApiKey $apiKey): JsonResponse
    {
        $w=$request->attributes->get('workspace');abort_unless($apiKey->workspace_id===$w->id,404);abort_if($apiKey->revoked_at,422,'A revoked API key cannot be rotated.');$plain='wiax_'.Str::random(64);
        $successor=\Illuminate\Support\Facades\DB::transaction(function()use($request,$apiKey,$plain){$apiKey->update(['revoked_at'=>now()]);return WorkspaceApiKey::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$apiKey->workspace_id,'created_by'=>$request->user()->id,'name'=>$apiKey->name,'prefix'=>substr($plain,0,13),'token_hash'=>hash('sha256',$plain),'scopes'=>$apiKey->scopes,'expires_at'=>now()->addDays((int)config('workintel_security.api.token_days',365)),'created_at'=>now()]);});
        return response()->json(['data'=>$this->apiKeyPayload($successor),'token'=>$plain,'revoked_key_id'=>$apiKey->id,'message'=>'API key rotated. The previous credential was revoked.']);
    }
    /** Handles the revoke api key operation for the current WorkIntel workflow. */ public function revokeApiKey(Request $request,WorkspaceApiKey $apiKey): JsonResponse { $w=$request->attributes->get('workspace');abort_unless($apiKey->workspace_id===$w->id,404);$apiKey->update(['revoked_at'=>now()]);return response()->json(['message'=>'API key revoked.']); }
    /** Handles the store webhook operation for the current WorkIntel workflow. */ public function storeWebhook(Request $request,OutboundUrlGuard $guard): JsonResponse
    {
        $w=$request->attributes->get('workspace');$data=$request->validate(['name'=>['required','string','max:120'],'url'=>['required','url','max:1000'],'events'=>['required','array','min:1','max:30'],'events.*'=>['string'],'max_attempts'=>['nullable','integer','min:1','max:10']]);$guard->assertSafe($data['url']);$this->validateEvents($data['events']);$secret='whsec_'.Str::random(48);
        $x=WebhookEndpoint::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$w->id,'created_by'=>$request->user()->id,'name'=>$data['name'],'url'=>$data['url'],'secret_encrypted'=>Crypt::encryptString($secret),'secret_preview'=>'••••'.substr($secret,-6),'events'=>array_values(array_unique($data['events'])),'status'=>'active','max_attempts'=>$data['max_attempts']??5]);
        return response()->json(['data'=>$this->webhookPayload($x),'signing_secret'=>$secret],201);
    }
    /** Updates update webhook data for the requested resource. */ public function updateWebhook(Request $request,WebhookEndpoint $webhook,OutboundUrlGuard $guard): JsonResponse
    {
        $w=$request->attributes->get('workspace');abort_unless($webhook->workspace_id===$w->id,404);$data=$request->validate(['name'=>['sometimes','string','max:120'],'url'=>['sometimes','url','max:1000'],'events'=>['sometimes','array','min:1','max:30'],'events.*'=>['string'],'status'=>['sometimes',Rule::in(['active','paused'])],'max_attempts'=>['sometimes','integer','min:1','max:10']]);if(isset($data['url']))$guard->assertSafe($data['url']);if(isset($data['events']))$this->validateEvents($data['events']);$webhook->update($data);return response()->json(['data'=>$this->webhookPayload($webhook->fresh())]);
    }
    /** Handles the destroy webhook operation for the current WorkIntel workflow. */ public function destroyWebhook(Request $request,WebhookEndpoint $webhook): JsonResponse { $w=$request->attributes->get('workspace');abort_unless($webhook->workspace_id===$w->id,404);$webhook->delete();return response()->json(null,204); }
    /** Handles the test webhook operation for the current WorkIntel workflow. */ public function testWebhook(Request $request,WebhookEndpoint $webhook,WebhookService $service): JsonResponse
    {
        $w=$request->attributes->get('workspace');abort_unless($webhook->workspace_id===$w->id,404);$delivery=WebhookDelivery::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$w->id,'webhook_endpoint_id'=>$webhook->id,'event_type'=>'workspace.activity','event_id'=>(string)Str::uuid(),'payload'=>['id'=>(string)Str::uuid(),'type'=>'workspace.activity','workspace_id'=>$w->id,'created_at'=>now()->toIso8601String(),'data'=>['test'=>true]],'status'=>'pending','next_attempt_at'=>now(),'created_at'=>now()]);$ok=$service->deliver($delivery);return response()->json(['data'=>$delivery->fresh(),'delivered'=>$ok],$ok?200:422);
    }
    /** Handles the deliveries operation for the current WorkIntel workflow. */ public function deliveries(Request $request,WebhookEndpoint $webhook): JsonResponse { $w=$request->attributes->get('workspace');abort_unless($webhook->workspace_id===$w->id,404);return response()->json(['data'=>$webhook->deliveries()->latest('created_at')->limit(100)->get()]); }
    /** Handles the retry delivery operation for the current WorkIntel workflow. */ public function retryDelivery(Request $request,WebhookDelivery $delivery,WebhookService $service): JsonResponse { $w=$request->attributes->get('workspace');abort_unless($delivery->workspace_id===$w->id,404);$delivery->update(['status'=>'pending','next_attempt_at'=>now(),'failed_at'=>null]);$ok=$service->deliver($delivery);return response()->json(['data'=>$delivery->fresh(),'delivered'=>$ok],$ok?200:422); }
    /** Handles the audit logs operation for the current WorkIntel workflow. */ public function auditLogs(Request $request): JsonResponse
    {
        $w=$request->attributes->get('workspace');$data=$request->validate(['category'=>['nullable','string','max:40'],'risk'=>['nullable','string','max:20'],'search'=>['nullable','string','max:120']]);$q=AuditLog::where('workspace_id',$w->id)->latest('created_at');if(!empty($data['category']))$q->where('category',$data['category']);if(!empty($data['risk']))$q->where('risk_level',$data['risk']);if(!empty($data['search'])){$s=$data['search'];$q->where(fn($x)=>$x->where('action','like',"%$s%")->orWhere('path','like',"%$s%"));}return response()->json(['data'=>$q->limit(500)->get()]);
    }
    /** Handles the security events operation for the current WorkIntel workflow. */ public function securityEvents(Request $request): JsonResponse { $w=$request->attributes->get('workspace');if (! Schema::hasTable('security_events')) return response()->json(['data'=>[],'storage_available'=>false]);return response()->json(['data'=>SecurityEvent::where('workspace_id',$w->id)->latest('created_at')->limit(300)->get(),'storage_available'=>true]); }
    /** Returns resolve security event data required by the current workflow. */ public function resolveSecurityEvent(Request $request,SecurityEvent $securityEvent): JsonResponse { $w=$request->attributes->get('workspace');abort_unless($securityEvent->workspace_id===$w->id,404);$securityEvent->update(['resolved_at'=>now(),'resolved_by'=>$request->user()->id]);return response()->json(['data'=>$securityEvent->fresh()]); }

    /** Validates validate events input before it is processed. */ private function validateEvents(array $events): void { foreach($events as $event){$valid=$event==='*'||in_array($event,WebhookService::EVENTS,true)||preg_match('/^[a-z0-9_]+(?:\.[a-z0-9_*:-]+)+$/i',(string)$event);if(!$valid)abort(422,"Unsupported webhook event: {$event}");} }
    /** Handles the api key payload operation for the current WorkIntel workflow. */ private function apiKeyPayload(WorkspaceApiKey $x): array { return ['id'=>$x->id,'uuid'=>$x->uuid,'name'=>$x->name,'prefix'=>$x->prefix,'scopes'=>$x->scopes,'last_used_at'=>$x->last_used_at?->toIso8601String(),'last_used_ip'=>$x->last_used_ip,'expires_at'=>$x->expires_at?->toIso8601String(),'revoked_at'=>$x->revoked_at?->toIso8601String(),'created_at'=>$x->created_at?->toIso8601String()]; }
    /** Handles the webhook payload operation for the current WorkIntel workflow. */ private function webhookPayload(WebhookEndpoint $x): array { return ['id'=>$x->id,'uuid'=>$x->uuid,'name'=>$x->name,'url'=>$x->url,'secret_preview'=>$x->secret_preview,'events'=>$x->events,'status'=>$x->status,'max_attempts'=>$x->max_attempts,'last_success_at'=>$x->last_success_at?->toIso8601String(),'last_failure_at'=>$x->last_failure_at?->toIso8601String(),'deliveries_count'=>$x->deliveries_count??null,'created_at'=>$x->created_at?->toIso8601String()]; }
    /** Handles the integration payload operation for the current WorkIntel workflow. */ private function integrationPayload(IntegrationConnection $x): array { $config=$x->config_encrypted?json_decode(Crypt::decryptString($x->config_encrypted),true):[];return ['id'=>$x->id,'uuid'=>$x->uuid,'provider'=>$x->provider,'name'=>$x->name,'status'=>$x->status,'config_preview'=>collect($config)->mapWithKeys(fn($v,$k)=>[$k=>preg_match('/token|secret|password|webhook_url|api[_-]?key|credential/i',$k)?'••••'.substr((string)$v,-4):(string)$v]),'last_tested_at'=>$x->last_tested_at?->toIso8601String(),'last_error'=>$x->last_error]; }

}
