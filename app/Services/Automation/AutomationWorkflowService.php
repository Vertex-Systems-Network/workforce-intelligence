<?php
namespace App\Services\Automation;

use App\Models\AutomationWorkflow;
use App\Models\IntegrationConnection;
use App\Models\Workspace;
use App\Services\Billing\EntitlementService;
use App\Services\Security\OutboundUrlGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides automation workflow service behavior within the WorkIntel application. */ class AutomationWorkflowService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly EntitlementService $entitlements,private readonly ConnectorRegistry $connectors,private readonly AutomationScheduleCalculator $schedules,private readonly OutboundUrlGuard $urlGuard){}
    /** Handles the save operation for the current WorkIntel workflow. */ public function save(Workspace $workspace,array $data,int $userId,?AutomationWorkflow $workflow=null): AutomationWorkflow
    {
        if(!$workflow){$limit=(int)$this->entitlements->value($workspace,'limit.automation_workflows',0);$count=AutomationWorkflow::where('workspace_id',$workspace->id)->where('status','!=','archived')->count();if($limit>=0&&$count>=$limit)throw ValidationException::withMessages(['plan'=>["Your plan allows {$limit} automation workflow(s)."]]);}
        $triggerType=$data['trigger_type']??'event';if(!in_array($triggerType,['event','incoming','schedule'],true))throw ValidationException::withMessages(['trigger_type'=>['Choose event, incoming, or schedule.']]);
        $triggerEvent=$data['trigger_event']??null;if($triggerType==='event'&&(!$triggerEvent||!preg_match('/^[A-Za-z0-9_.*:-]{1,120}$/',$triggerEvent)))throw ValidationException::withMessages(['trigger_event'=>['Provide a valid event name or wildcard pattern.']]);
        $conditions=array_values($data['conditions']??[]);if(count($conditions)>20)throw ValidationException::withMessages(['conditions'=>['Maximum 20 conditions per workflow.']]);foreach($conditions as $i=>$c){if(empty($c['field'])||!preg_match('/^[A-Za-z0-9_.-]{1,160}$/',(string)$c['field']))throw ValidationException::withMessages(["conditions.$i.field"=>['Use a valid context path.']]);if(!in_array($c['operator']??'',AutomationCatalog::CONDITION_OPERATORS,true))throw ValidationException::withMessages(["conditions.$i.operator"=>['Unsupported condition operator.']]);}
        $actions=array_values($data['actions']??[]);if(!$actions||count($actions)>20)throw ValidationException::withMessages(['actions'=>['Add between 1 and 20 actions.']]);foreach($actions as $i=>&$action){$this->validateAction($workspace,$action,$i);$action['position']=$i+1;}
        $triggerConfig=(array)($data['trigger_config']??[]);$nextRun=null;if($triggerType==='schedule')$nextRun=$this->schedules->next($triggerConfig,$workspace->timezone?:'UTC');
        return DB::transaction(function()use($workspace,$data,$userId,$workflow,$triggerType,$triggerEvent,$conditions,$actions,$triggerConfig,$nextRun){
            $values=['workspace_id'=>$workspace->id,'name'=>trim((string)$data['name']),'description'=>$data['description']??null,'status'=>$data['status']??'draft','trigger_type'=>$triggerType,'trigger_event'=>$triggerEvent,'trigger_config'=>$triggerConfig,'conditions'=>$conditions,'condition_mode'=>$data['condition_mode']??'all','failure_policy'=>$data['failure_policy']??'stop','max_run_seconds'=>max(5,min(120,(int)($data['max_run_seconds']??30))),'next_run_at'=>($data['status']??'draft')==='active'&&$triggerType==='schedule'?$nextRun:null,'updated_by'=>$userId];
            if(!$workflow){$values['uuid']=(string)Str::uuid();$values['created_by']=$userId;$workflow=AutomationWorkflow::create($values);}else{$workflow->update($values);$workflow->actions()->delete();}
            foreach($actions as $action)$workflow->actions()->create(['position'=>$action['position'],'name'=>$action['name']??('Action '.$action['position']),'action_type'=>$action['action_type'],'action_key'=>$action['action_key']??'','integration_connection_id'=>$action['integration_connection_id']??null,'config'=>$action['config']??[],'continue_on_error'=>(bool)($action['continue_on_error']??false),'retry_max'=>max(0,min(5,(int)($action['retry_max']??2))),'timeout_seconds'=>max(3,min(30,(int)($action['timeout_seconds']??12)))]);
            return $workflow->fresh(['actions.integration']);
        });
    }
    /** Validates validate action input before it is processed. */ private function validateAction(Workspace $workspace,array &$action,int $index): void
    {
        $type=$action['action_type']??'';if(!in_array($type,['connector','webhook','notification','task.create'],true))throw ValidationException::withMessages(["actions.$index.action_type"=>['Unsupported action type.']]);
        if($type==='connector'){$id=(int)($action['integration_connection_id']??0);$integration=IntegrationConnection::where('workspace_id',$workspace->id)->find($id);if(!$integration)throw ValidationException::withMessages(["actions.$index.integration_connection_id"=>['Choose a workspace connector.']]);$keys=collect($this->connectors->driver($integration->provider)->catalog()['actions'])->pluck('key')->all();if(!in_array($action['action_key']??'',$keys,true))throw ValidationException::withMessages(["actions.$index.action_key"=>['Choose an action supported by this connector.']]);}
        if($type==='webhook'){if(empty($action['config']['url']))throw ValidationException::withMessages(["actions.$index.config.url"=>['Webhook URL is required.']]);try{$this->urlGuard->assertSafe((string)$action['config']['url']);}catch(\Throwable $e){throw ValidationException::withMessages(["actions.$index.config.url"=>[$e->getMessage()]]);}if(isset($action['config']['headers'])&&!is_array($action['config']['headers']))throw ValidationException::withMessages(["actions.$index.config.headers"=>['Webhook headers must be a JSON object.']]);foreach((array)($action['config']['headers']??[]) as $key=>$value){if(!is_string($key)||!preg_match('/^[A-Za-z0-9-]{1,80}$/',$key))throw ValidationException::withMessages(["actions.$index.config.headers"=>['Use valid HTTP header names.']]);if(preg_match('/authorization|cookie|token|secret|api[-_]?key/i',$key))throw ValidationException::withMessages(["actions.$index.config.headers"=>['Store authenticated HTTP credentials in an encrypted Generic HTTP connector instead of a direct webhook action.']]);}if(array_key_exists('bearer_token',(array)($action['config']??[])))throw ValidationException::withMessages(["actions.$index.config"=>['Store bearer credentials in an encrypted Generic HTTP connector.']]);}
        if($type==='task.create'&&empty($action['config']['project_id']))throw ValidationException::withMessages(["actions.$index.config.project_id"=>['Project is required for task creation.']]);
    }
}
