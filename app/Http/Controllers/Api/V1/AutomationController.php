<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AutomationDeadLetter;
use App\Models\AutomationIncomingHook;
use App\Models\AutomationRun;
use App\Models\AutomationWorkflow;
use App\Models\IntegrationConnection;
use App\Models\Project;
use App\Models\WorkspaceMember;
use App\Services\Automation\AutomationCatalog;
use App\Services\Automation\AutomationEngine;
use App\Services\Automation\AutomationWorkflowService;
use App\Services\Automation\ConnectorRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Provides automation controller behavior within the WorkIntel application. */ class AutomationController extends Controller
{
    /** Handles the overview operation for the current WorkIntel workflow. */ public function overview(Request $request,ConnectorRegistry $connectors): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$member=$request->attributes->get('workspaceMember');$canManage=$member->hasPermission('automations.manage');
        $ready=Schema::hasTable('automation_workflows')&&Schema::hasTable('automation_runs')&&Schema::hasTable('automation_incoming_hooks');
        if(!$ready)return response()->json(['schema_ready'=>false,'workflows'=>[],'runs'=>[],'dead_letters'=>[],'hooks'=>[],'integrations'=>[],'connectors'=>$connectors->catalog(),'triggers'=>AutomationCatalog::triggerEvents(),'templates'=>AutomationCatalog::templates(),'can_manage'=>$canManage]);
        $workflows=AutomationWorkflow::where('workspace_id',$workspace->id)->with(['actions.integration:id,provider,name,status'])->withCount(['actions','runs'])->latest()->limit(200)->get();
        $runs=AutomationRun::where('workspace_id',$workspace->id)->with('workflow:id,name')->latest('created_at')->limit(100)->get();
        $hooks=AutomationIncomingHook::where('workspace_id',$workspace->id)->with('workflow:id,name')->latest('created_at')->limit(100)->get()->map(fn($h)=>$this->hookPayload($h));
        $dead=AutomationDeadLetter::where('workspace_id',$workspace->id)->whereNull('resolved_at')->with('run.workflow:id,name')->latest('created_at')->limit(100)->get();
        return response()->json(['schema_ready'=>true,'workflows'=>$workflows,'runs'=>$runs,'dead_letters'=>$dead,'hooks'=>$hooks,'integrations'=>IntegrationConnection::where('workspace_id',$workspace->id)->orderBy('name')->get(['id','uuid','provider','name','status','last_tested_at','last_error']),'connectors'=>$connectors->catalog(),'triggers'=>AutomationCatalog::triggerEvents(),'condition_operators'=>AutomationCatalog::CONDITION_OPERATORS,'templates'=>AutomationCatalog::templates(),'projects'=>$canManage?Project::where('workspace_id',$workspace->id)->orderBy('name')->get(['id','name','code']):[],'people'=>$canManage?WorkspaceMember::where('workspace_id',$workspace->id)->with('user:id,first_name,last_name')->where('status','active')->get(['id','user_id']):[],'can_manage'=>$canManage]);
    }

    /** Creates and persists the requested resource. */ public function store(Request $request,AutomationWorkflowService $service): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$data=$this->workflowData($request);$workflow=$service->save($workspace,$data,$request->user()->id);return response()->json(['data'=>$workflow],201);
    }
    /** Updates update data for the requested resource. */ public function update(Request $request,AutomationWorkflow $workflow,AutomationWorkflowService $service): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$this->owned($workflow,$workspace->id);$data=$this->workflowData($request);return response()->json(['data'=>$service->save($workspace,$data,$request->user()->id,$workflow)]);
    }
    /** Removes destroy data from the requested resource. */ public function destroy(Request $request,AutomationWorkflow $workflow): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$this->owned($workflow,$workspace->id);if($workflow->runs()->exists()){$workflow->update(['status'=>'archived','updated_by'=>$request->user()->id,'next_run_at'=>null]);return response()->json(['message'=>'Workflow archived to preserve run history.','data'=>$workflow->fresh()]);}$workflow->delete();return response()->json(null,204);
    }
    /** Handles the test operation for the current WorkIntel workflow. */ public function test(Request $request,AutomationWorkflow $workflow,AutomationEngine $engine): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$this->owned($workflow,$workspace->id);$data=$request->validate(['payload'=>['nullable','array','max:100']]);$run=$engine->test($workflow,$data['payload']??['test'=>true]);return response()->json(['data'=>$run]);
    }
    /** Handles the runs operation for the current WorkIntel workflow. */ public function runs(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$data=$request->validate(['status'=>['nullable','string',Rule::in(['queued','running','succeeded','partial','failed','retrying'])],'workflow_id'=>['nullable','integer']]);$q=AutomationRun::where('workspace_id',$workspace->id)->with('workflow:id,name')->latest('created_at');if(!empty($data['status']))$q->where('status',$data['status']);if(!empty($data['workflow_id']))$q->where('automation_workflow_id',$data['workflow_id']);return response()->json(['data'=>$q->limit(300)->get()]);
    }
    /** Handles the show run operation for the current WorkIntel workflow. */ public function showRun(Request $request,AutomationRun $run): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless($run->workspace_id===$workspace->id,404);return response()->json(['data'=>$run->load(['workflow:id,name,uuid','steps.action:id,name,action_type,action_key','deadLetter'])]);
    }
    /** Handles the retry run operation for the current WorkIntel workflow. */ public function retryRun(Request $request,AutomationRun $run,AutomationEngine $engine): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless($run->workspace_id===$workspace->id,404);$dead=$run->deadLetter()->whereNull('resolved_at')->first();abort_unless($dead,422,'Only unresolved dead-lettered runs can be retried.');return response()->json(['data'=>$engine->retryDeadLetter($dead,$request->user()->id)],201);
    }
    /** Handles the dead letters operation for the current WorkIntel workflow. */ public function deadLetters(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');return response()->json(['data'=>AutomationDeadLetter::where('workspace_id',$workspace->id)->with('run.workflow:id,name')->latest('created_at')->limit(300)->get()]);
    }
    /** Returns resolve dead letter data required by the current workflow. */ public function resolveDeadLetter(Request $request,AutomationDeadLetter $deadLetter): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless($deadLetter->workspace_id===$workspace->id,404);$deadLetter->update(['resolved_at'=>now(),'resolved_by'=>$request->user()->id]);return response()->json(['data'=>$deadLetter->fresh()]);
    }
    /** Handles the store hook operation for the current WorkIntel workflow. */ public function storeHook(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$data=$request->validate(['name'=>['required','string','max:140'],'event_name'=>['required','string','max:120','regex:/^[A-Za-z0-9_.*:-]+$/'],'workflow_id'=>['nullable','integer'],'rate_limit_per_minute'=>['nullable','integer','min:1','max:600']]);if(!empty($data['workflow_id']))abort_unless(AutomationWorkflow::where('workspace_id',$workspace->id)->whereKey($data['workflow_id'])->exists(),422,'Workflow does not belong to this workspace.');[$plain,$prefix,$hash]=$this->newHookToken();$hook=AutomationIncomingHook::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'automation_workflow_id'=>$data['workflow_id']??null,'name'=>$data['name'],'event_name'=>$data['event_name'],'token_prefix'=>$prefix,'token_hash'=>$hash,'status'=>'active','rate_limit_per_minute'=>$data['rate_limit_per_minute']??60,'created_by'=>$request->user()->id,'created_at'=>now()]);return response()->json(['data'=>$this->hookPayload($hook->fresh('workflow')),'token'=>$plain],201);
    }
    /** Handles the rotate hook operation for the current WorkIntel workflow. */ public function rotateHook(Request $request,AutomationIncomingHook $hook): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless($hook->workspace_id===$workspace->id,404);[$plain,$prefix,$hash]=$this->newHookToken();$hook->update(['token_prefix'=>$prefix,'token_hash'=>$hash,'last_used_at'=>null,'last_used_ip'=>null]);return response()->json(['data'=>$this->hookPayload($hook->fresh('workflow')),'token'=>$plain]);
    }
    /** Updates update hook data for the requested resource. */ public function updateHook(Request $request,AutomationIncomingHook $hook): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless($hook->workspace_id===$workspace->id,404);$data=$request->validate(['name'=>['sometimes','string','max:140'],'event_name'=>['sometimes','string','max:120','regex:/^[A-Za-z0-9_.*:-]+$/'],'workflow_id'=>['nullable','integer'],'status'=>['sometimes',Rule::in(['active','paused'])],'rate_limit_per_minute'=>['sometimes','integer','min:1','max:600']]);if(array_key_exists('workflow_id',$data)&&$data['workflow_id']!==null)abort_unless(AutomationWorkflow::where('workspace_id',$workspace->id)->whereKey($data['workflow_id'])->exists(),422,'Workflow does not belong to this workspace.');$values=collect($data)->except('workflow_id')->all();if(array_key_exists('workflow_id',$data))$values['automation_workflow_id']=$data['workflow_id'];$hook->update($values);return response()->json(['data'=>$this->hookPayload($hook->fresh('workflow'))]);
    }
    /** Handles the destroy hook operation for the current WorkIntel workflow. */ public function destroyHook(Request $request,AutomationIncomingHook $hook): JsonResponse { $workspace=$request->attributes->get('workspace');abort_unless($hook->workspace_id===$workspace->id,404);$hook->delete();return response()->json(null,204); }

    /** Handles the workflow data operation for the current WorkIntel workflow. */ private function workflowData(Request $request): array
    {
        return $request->validate(['name'=>['required','string','max:160'],'description'=>['nullable','string','max:2000'],'status'=>['required',Rule::in(['draft','active','paused'])],'trigger_type'=>['required',Rule::in(['event','incoming','schedule'])],'trigger_event'=>['nullable','string','max:120'],'trigger_config'=>['nullable','array','max:20'],'conditions'=>['nullable','array','max:20'],'conditions.*.field'=>['required_with:conditions','string','max:160'],'conditions.*.operator'=>['required_with:conditions','string','max:20'],'conditions.*.value'=>['nullable'],'condition_mode'=>['nullable',Rule::in(['all','any'])],'failure_policy'=>['nullable',Rule::in(['stop','continue'])],'max_run_seconds'=>['nullable','integer','min:5','max:120'],'actions'=>['required','array','min:1','max:20'],'actions.*.name'=>['nullable','string','max:140'],'actions.*.action_type'=>['required','string','max:30'],'actions.*.action_key'=>['nullable','string','max:100'],'actions.*.integration_connection_id'=>['nullable','integer'],'actions.*.config'=>['nullable','array','max:50'],'actions.*.continue_on_error'=>['nullable','boolean'],'actions.*.retry_max'=>['nullable','integer','min:0','max:5'],'actions.*.timeout_seconds'=>['nullable','integer','min:3','max:30']]);
    }
    /** Handles the owned operation for the current WorkIntel workflow. */ private function owned(AutomationWorkflow $workflow,int $workspaceId): void { abort_unless($workflow->workspace_id===$workspaceId,404); }
    /** Handles the new hook token operation for the current WorkIntel workflow. */ private function newHookToken(): array { $plain='wiin_'.Str::random(58);return [$plain,substr($plain,0,13),hash('sha256',$plain)]; }
    /** Handles the hook payload operation for the current WorkIntel workflow. */ private function hookPayload(AutomationIncomingHook $hook): array { return ['id'=>$hook->id,'uuid'=>$hook->uuid,'name'=>$hook->name,'event_name'=>$hook->event_name,'workflow_id'=>$hook->automation_workflow_id,'workflow'=>$hook->workflow?['id'=>$hook->workflow->id,'name'=>$hook->workflow->name]:null,'token_prefix'=>$hook->token_prefix,'status'=>$hook->status,'rate_limit_per_minute'=>$hook->rate_limit_per_minute,'last_used_at'=>$hook->last_used_at?->toIso8601String(),'last_used_ip'=>$hook->last_used_ip,'endpoint'=>url('/api/incoming/v1/automations/'.$hook->uuid),'created_at'=>$hook->created_at?->toIso8601String()]; }
}
