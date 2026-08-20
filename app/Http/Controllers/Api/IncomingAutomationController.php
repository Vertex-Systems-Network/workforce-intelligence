<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutomationIncomingHook;
use App\Models\Workspace;
use App\Services\Automation\AutomationEngine;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Provides incoming automation controller behavior within the WorkIntel application. */ class IncomingAutomationController extends Controller
{
    /** Handles the receive operation for the current WorkIntel workflow. */ public function receive(Request $request,string $uuid,AutomationEngine $engine,EntitlementService $entitlements): JsonResponse
    {
        $hook=AutomationIncomingHook::where('uuid',$uuid)->where('status','active')->first();abort_unless($hook,404);
        $workspace=Workspace::findOrFail($hook->workspace_id);$entitlements->assertFeature($workspace,'feature.automations');app(\App\Services\Modules\WorkspaceModuleService::class)->assertEnabled($workspace,'automations');
        $plain=$request->bearerToken();if(!$plain||!hash_equals($hook->token_hash,hash('sha256',$plain)))return response()->json(['message'=>'Invalid incoming automation token.'],401);
        if((int)$request->server('CONTENT_LENGTH',0)>262144)return response()->json(['message'=>'Incoming payload exceeds 256 KB.'],413);
        $rateKey='automation-hook:'.$hook->id.':'.$request->ip();if(!RateLimiter::attempt($rateKey,max(1,$hook->rate_limit_per_minute),fn()=>true,60))return response()->json(['message'=>'Incoming automation rate limit exceeded.'],429);
        $payload=$request->json()->all();if(!is_array($payload)||array_is_list($payload))return response()->json(['message'=>'Send a JSON object payload.'],422);if(strlen(json_encode($payload,JSON_UNESCAPED_SLASHES)?:'')>262144)return response()->json(['message'=>'Incoming payload exceeds 256 KB.'],413);
        $idempotency=Str::limit((string)($request->header('X-WorkIntel-Idempotency-Key')??$payload['idempotency_key']??$payload['id']??''),180,'');if($idempotency==='')$idempotency=null;
        $event=$engine->emit($workspace,$hook->event_name,$payload,'incoming:'.$hook->uuid,$idempotency,$hook->automation_workflow_id);
        $hook->update(['last_used_at'=>now(),'last_used_ip'=>$request->ip()]);
        return response()->json(['accepted'=>true,'event_id'=>$event?->uuid,'event_type'=>$hook->event_name],202);
    }
}
