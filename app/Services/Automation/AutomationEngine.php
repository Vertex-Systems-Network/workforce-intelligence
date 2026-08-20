<?php
namespace App\Services\Automation;

use App\Models\AutomationDeadLetter;
use App\Models\AutomationEvent;
use App\Models\AutomationRun;
use App\Models\AutomationRunStep;
use App\Models\AutomationWorkflow;
use App\Models\IntegrationConnection;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\EntitlementService;
use App\Services\Notifications\WorkspaceNotificationService;
use App\Services\Security\OutboundUrlGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides automation engine behavior within the WorkIntel application. */ class AutomationEngine
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(
        private readonly EntitlementService $entitlements,
        private readonly AutomationConditionEvaluator $conditions,
        private readonly AutomationTemplateRenderer $renderer,
        private readonly AutomationScheduleCalculator $schedules,
        private readonly ConnectorRegistry $connectors,
        private readonly WorkspaceNotificationService $notifications,
        private readonly OutboundUrlGuard $urlGuard,
    ) {}

    /** Handles the installed operation for the current WorkIntel workflow. */ public function installed(): bool
    {
        return Schema::hasTable('automation_workflows') && Schema::hasTable('automation_runs') && Schema::hasTable('automation_events');
    }

    /** Handles the emit operation for the current WorkIntel workflow. */ public function emit(Workspace $workspace,string $eventType,array $payload,string $source='workspace',?string $idempotencyKey=null,?int $onlyWorkflowId=null): ?AutomationEvent
    {
        if(!$this->installed() || !app(\App\Services\Modules\WorkspaceModuleService::class)->shouldProcessBackground($workspace,'automations') || !$this->entitlements->allows($workspace,'feature.automations')) return null;
        if($idempotencyKey!==null && mb_strlen($idempotencyKey)>180) $idempotencyKey=hash('sha256',$idempotencyKey);
        if($idempotencyKey!==null){
            $existing=AutomationEvent::query()->where('workspace_id',$workspace->id)->where('source',$source)->where('idempotency_key',$idempotencyKey)->first();
            if($existing) return $existing;
        }
        try {
            $event=AutomationEvent::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'event_type'=>$eventType,'source'=>$source,'idempotency_key'=>$idempotencyKey,'payload'=>$payload,'occurred_at'=>now(),'created_at'=>now()]);
        } catch (QueryException $e) {
            // Concurrent callers may race on the workspace/source/idempotency unique key.
            // Resolve the already-created event instead of surfacing a duplicate-key 500.
            if ($idempotencyKey !== null) {
                $existing=AutomationEvent::query()->where('workspace_id',$workspace->id)->where('source',$source)->where('idempotency_key',$idempotencyKey)->first();
                if ($existing) return $existing;
            }
            throw $e;
        }
        $context=$this->baseContext($workspace,$eventType,$payload,$event);
        $query=AutomationWorkflow::query()->where('workspace_id',$workspace->id)->where('status','active');
        if($onlyWorkflowId)$query->whereKey($onlyWorkflowId);else$query->whereIn('trigger_type',['event','incoming']);
        foreach($query->with('actions')->get() as $workflow){
            if(!$onlyWorkflowId && !Str::is($workflow->trigger_event?:'', $eventType)) continue;
            if(!$this->conditions->passes($workflow->conditions??[],$workflow->condition_mode?:'all',$context)) continue;
            $this->createRun($workflow,$event,$eventType,$payload,['source'=>$source]);
        }
        $event->update(['processed_at'=>now()]);
        return $event;
    }

    /** Handles the test operation for the current WorkIntel workflow. */ public function test(AutomationWorkflow $workflow,array $payload): AutomationRun
    {
        $workflow->loadMissing('actions');$workspace=Workspace::findOrFail($workflow->workspace_id);
        $run=$this->createRun($workflow,null,'automation.test',$payload,['test'=>true]);
        return $this->processRun($run);
    }

    /** Handles the process due operation for the current WorkIntel workflow. */ public function processDue(int $limit=100): array
    {
        if(!$this->installed())return ['scheduled'=>0,'processed'=>0,'failed'=>0];
        $scheduled=0;$processed=0;$failed=0;
        AutomationWorkflow::query()->where('status','active')->where('trigger_type','schedule')->whereNotNull('next_run_at')->where('next_run_at','<=',now())->orderBy('next_run_at')->limit(100)->get()->each(function(AutomationWorkflow $workflow)use(&$scheduled){
            $workspace=Workspace::find($workflow->workspace_id);if(!$workspace||!app(\App\Services\Modules\WorkspaceModuleService::class)->shouldProcessBackground($workspace,'automations')||!$this->entitlements->allows($workspace,'feature.automations'))return;
            $due=$workflow->next_run_at; $idem='schedule:'.$workflow->id.':'.$due?->toIso8601String();
            $event=AutomationEvent::query()->where('workspace_id',$workspace->id)->where('source','schedule')->where('idempotency_key',$idem)->first();
            if(!$event){$event=AutomationEvent::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'event_type'=>'schedule.'.$workflow->uuid,'source'=>'schedule','idempotency_key'=>$idem,'payload'=>['scheduled_for'=>$due?->toIso8601String()],'occurred_at'=>now(),'processed_at'=>now(),'created_at'=>now()]);$this->createRun($workflow,$event,'schedule.'.$workflow->uuid,$event->payload,['scheduled'=>true]);$scheduled++;}
            $workflow->update(['next_run_at'=>$this->schedules->next($workflow->trigger_config??[],$workspace->timezone?:'UTC',$due??now())]);
        });
        foreach(AutomationRun::query()->whereIn('status',['queued','retrying'])->where(fn($q)=>$q->whereNull('next_attempt_at')->orWhere('next_attempt_at','<=',now()))->orderBy('created_at')->limit($limit)->get() as $run){
            $result=$this->processRun($run);$processed++;if($result->status==='failed')$failed++;
        }
        return compact('scheduled','processed','failed');
    }

    /** Handles the process run operation for the current WorkIntel workflow. */ public function processRun(AutomationRun $run): AutomationRun
    {
        $run=AutomationRun::query()->with(['workflow.actions.integration','workflow'])->findOrFail($run->id);$workflow=$run->workflow;
        if(!$workflow){$run->update(['status'=>'failed','error'=>'Workflow no longer exists.','completed_at'=>now()]);return $run->fresh();}
        $workspace=Workspace::findOrFail($run->workspace_id);if(!app(\App\Services\Modules\WorkspaceModuleService::class)->shouldProcessBackground($workspace,'automations')){ $run->update(['status'=>'cancelled','error'=>'Automation module is disabled.','completed_at'=>now(),'next_attempt_at'=>null]); return $run->fresh(['steps','workflow']); }$started=microtime(true);$run->update(['status'=>'running','attempts'=>$run->attempts+1,'started_at'=>now(),'next_attempt_at'=>null,'error'=>null]);
        $context=$this->baseContext($workspace,$run->trigger_event??'automation.manual',$run->trigger_payload??[], $run->event);
        $context['run']=['id'=>$run->id,'uuid'=>$run->uuid];$context['steps']=[];$failedSteps=0;
        foreach($workflow->actions as $action){
            if((microtime(true)-$started)>max(5,(int)$workflow->max_run_seconds)){return $this->failRun($run,'Workflow exceeded its maximum run time.',$context);}
            $rendered=$this->renderer->render($action->config??[],$context);$step=AutomationRunStep::create(['automation_run_id'=>$run->id,'automation_action_id'=>$action->id,'position'=>$action->position,'name'=>$action->name,'status'=>'running','input'=>$this->sanitize($rendered),'attempts'=>0,'started_at'=>now()]);
            $lastError=null;$output=null;$maxAttempts=max(1,min(6,(int)$action->retry_max+1));
            for($attempt=1;$attempt<=$maxAttempts;$attempt++){
                $step->attempts=$attempt;$step->save();
                try{$output=$this->executeAction($workspace,$action,$rendered,$context);$lastError=null;break;}catch(\Throwable $e){$lastError=$e;if($attempt<$maxAttempts)usleep(100000);}
            }
            if($lastError){$failedSteps++;$step->update(['status'=>'failed','completed_at'=>now(),'error'=>Str::limit($lastError->getMessage(),2000,''),]);$context['steps'][(string)$action->position]=['status'=>'failed','error'=>$lastError->getMessage()];if(!$action->continue_on_error && $workflow->failure_policy!=='continue')return $this->failRun($run,$lastError->getMessage(),$context);continue;}
            $step->update(['status'=>'succeeded','output'=>$this->sanitize($output),'completed_at'=>now(),'error'=>null]);$context['steps'][(string)$action->position]=['status'=>'succeeded','output'=>$output];
        }
        $status=$failedSteps?'partial':'succeeded';$run->update(['status'=>$status,'context'=>$this->sanitize($context),'completed_at'=>now(),'error'=>null]);$workflow->update(['last_run_at'=>now()]);return $run->fresh(['steps','workflow']);
    }

    /** Handles the retry dead letter operation for the current WorkIntel workflow. */ public function retryDeadLetter(AutomationDeadLetter $dead,?int $userId=null): AutomationRun
    {
        $dead->loadMissing('run.workflow');abort_unless(!$dead->resolved_at,422,'Dead letter is already resolved.');$old=$dead->run;abort_unless($old&&$old->workflow,422,'Original workflow is no longer available.');
        $new=$this->createRun($old->workflow,$old->event,$old->trigger_event??'automation.retry',$old->trigger_payload??[],array_merge($old->context??[],['retry_of'=>$old->uuid]));
        $dead->update(['retry_count'=>$dead->retry_count+1,'resolved_at'=>now(),'resolved_by'=>$userId]);return $new;
    }

    /** Creates create run data for the requested workflow. */ private function createRun(AutomationWorkflow $workflow,?AutomationEvent $event,string $triggerEvent,array $payload,array $context=[]): AutomationRun
    {
        return AutomationRun::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workflow->workspace_id,'automation_workflow_id'=>$workflow->id,'automation_event_id'=>$event?->id,'trigger_event'=>$triggerEvent,'status'=>'queued','trigger_payload'=>$payload,'context'=>$context,'attempts'=>0,'next_attempt_at'=>now(),'created_at'=>now()]);
    }

    /** Handles the fail run operation for the current WorkIntel workflow. */ private function failRun(AutomationRun $run,string $message,array $context): AutomationRun
    {
        $run->update(['status'=>'failed','context'=>$this->sanitize($context),'completed_at'=>now(),'error'=>Str::limit($message,4000,''),'next_attempt_at'=>null]);
        AutomationDeadLetter::updateOrCreate(['automation_run_id'=>$run->id],['uuid'=>(string)Str::uuid(),'workspace_id'=>$run->workspace_id,'reason'=>Str::limit($message,160,''),'payload'=>['trigger_event'=>$run->trigger_event,'trigger_payload'=>$run->trigger_payload],'created_at'=>now()]);
        return $run->fresh(['steps','workflow','deadLetter']);
    }

    /** Handles the execute action operation for the current WorkIntel workflow. */ private function executeAction(Workspace $workspace,$action,array $config,array $context): array
    {
        return match($action->action_type){
            'connector'=>$this->connectorAction($workspace,$action,$config),
            'webhook'=>$this->webhookAction($config,(int)$action->timeout_seconds),
            'notification'=>$this->notificationAction($workspace,$config),
            'task.create'=>$this->taskAction($workspace,$config),
            default=>throw ValidationException::withMessages(['action_type'=>["Unsupported automation action type: {$action->action_type}"]]),
        };
    }

    /** Handles the connector action operation for the current WorkIntel workflow. */ private function connectorAction(Workspace $workspace,$action,array $input): array
    {
        /** @var IntegrationConnection|null $integration */$integration=$action->integration;
        if(!$integration||$integration->workspace_id!==$workspace->id||$integration->status!=='active')throw new \RuntimeException('Connector is missing or paused.');
        $config=json_decode(Crypt::decryptString($integration->config_encrypted),true)?:[];
        return $this->connectors->execute($integration->provider,$action->action_key,$config,$input,max(3,min(30,(int)$action->timeout_seconds)));
    }

    /** Handles the webhook action operation for the current WorkIntel workflow. */ private function webhookAction(array $config,int $timeout): array
    {
        $url=(string)($config['url']??'');$this->urlGuard->assertSafe($url);$headers=is_array($config['headers']??null)?$config['headers']:[];$body=$config['body']??[];if(!is_array($body))$body=['value'=>$body];
        $response=Http::timeout(max(3,min(30,$timeout)))->withHeaders($headers)->post($url,$body);
        if(!$response->successful())throw new \RuntimeException('Webhook action failed with HTTP '.$response->status().': '.Str::limit($response->body(),700,''));
        return ['ok'=>true,'status'=>$response->status(),'body'=>Str::limit($response->body(),1200,'')];
    }

    /** Handles the notification action operation for the current WorkIntel workflow. */ private function notificationAction(Workspace $workspace,array $config): array
    {
        $members=WorkspaceMember::query()->with(['user','roles'])->where('workspace_id',$workspace->id)->where('status','active');
        $memberIds=array_values(array_filter(array_map('intval',(array)($config['member_ids']??[]))));$roles=array_values(array_filter((array)($config['role_slugs']??[])));
        if($memberIds)$members->whereIn('id',$memberIds);
        elseif($roles)$members->whereHas('roles',fn($q)=>$q->whereIn('slug',$roles));
        else $members->whereHas('roles',fn($q)=>$q->whereIn('slug',['owner','admin']));
        $count=0;foreach($members->get() as $member){if(!$member->user)continue;$this->notifications->notify($workspace,$member->user,'workspace','automation.notification',(string)($config['title']??'WorkIntel automation'),isset($config['body'])?(string)$config['body']:null,(string)($config['severity']??'info'),['automation'=>true]);$count++;}
        return ['ok'=>true,'notified'=>$count];
    }

    /** Handles the task action operation for the current WorkIntel workflow. */ private function taskAction(Workspace $workspace,array $config): array
    {
        $project=Project::query()->where('workspace_id',$workspace->id)->findOrFail((int)($config['project_id']??0));$creator=(int)$workspace->owner_id;if(!$creator)throw new \RuntimeException('Automation task action needs a valid creator.');
        $workflow=app(\App\Services\Tasks\TaskWorkflowService::class);$workflow->ensureDefaults($workspace);$status=$workflow->resolveStatus($workspace->id,null,(string)($config['status']??'todo'));
        $content=app(\App\Services\Tasks\TaskContentService::class);$description=(string)($config['description']??'');$html=$content->sanitize($config['description_html']??null);$plain=$content->plainText($html,$description);
        $task=Task::create(['workspace_id'=>$workspace->id,'project_id'=>$project->id,'task_status_id'=>$status->id,'title'=>(string)($config['title']??'Automation task'),'description'=>$plain,'description_html'=>$html,'status'=>$status->slug,'priority'=>$config['priority']??'medium','estimated_minutes'=>isset($config['estimated_minutes'])?(int)$config['estimated_minutes']:null,'start_at'=>$config['start_at']??null,'due_at'=>$config['due_at']??null,'position'=>$workflow->nextPosition($workspace->id,$status->id),'billable'=>(bool)($config['billable']??false),'created_by'=>$creator,'completed_at'=>$status->is_completed?now():null]);
        $assignees=array_values(array_filter(array_map('intval',(array)($config['assignee_member_ids']??[]))));if($assignees){$valid=WorkspaceMember::query()->where('workspace_id',$workspace->id)->whereIn('id',$assignees)->pluck('id');$task->assignees()->sync($valid);}
        app(\App\Services\Tasks\TaskActivityService::class)->log($task,null,'created',['source'=>'automation']);
        return ['ok'=>true,'task_id'=>$task->id,'title'=>$task->title];
    }

    /** Handles the base context operation for the current WorkIntel workflow. */ private function baseContext(Workspace $workspace,string $eventType,array $payload,?AutomationEvent $event): array
    {
        return ['event'=>['id'=>$event?->uuid,'type'=>$eventType,'occurred_at'=>$event?->occurred_at?->toIso8601String()??now()->toIso8601String()],'payload'=>$payload,'workspace'=>['id'=>$workspace->id,'name'=>$workspace->name,'slug'=>$workspace->slug,'timezone'=>$workspace->timezone,'currency'=>$workspace->currency]];
    }

    /** Handles the sanitize operation for the current WorkIntel workflow. */ private function sanitize(mixed $value,int $depth=0): mixed
    {
        if($depth>5)return '[truncated]';if(is_array($value)){foreach($value as $key=>$item){$value[$key]=is_string($key)&&preg_match('/password|secret|token|authorization|cookie|credential|api[_-]?key/i',$key)?'[redacted]':$this->sanitize($item,$depth+1);}return $value;}if(is_string($value))return Str::limit($value,3000,'…');if(is_object($value))return '[object '.get_class($value).']';return $value;
    }
}
