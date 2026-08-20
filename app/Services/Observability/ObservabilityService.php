<?php

namespace App\Services\Observability;

use App\Models\BillingTransaction;
use App\Models\ClientPaymentCheckoutSession;
use App\Models\CommerceRefund;
use App\Models\ScreenshotStorageJob;
use App\Models\SystemBackupRun;
use App\Models\SystemObservabilityAlert;
use App\Models\SystemObservabilityAlertRule;
use App\Models\SystemObservabilityEvent;
use App\Models\SystemObservabilityHeartbeat;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Notifications\PlatformObservabilityAlertMail;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/** Centralizes privacy-safe platform observability capture, metrics, alerts and health summaries. */
class ObservabilityService
{
    private static bool $recording=false;

    /** Record one event, aggregating repeated fingerprints within the configured dedupe window. */
    public function record(string $category,string $eventType,string $message,string $severity='info',array $context=[],?int $workspaceId=null,?string $source=null,?float $durationMs=null,?string $fingerprint=null): ?SystemObservabilityEvent
    {
        if(self::$recording)return null;
        self::$recording=true;
        try{
            if(!Schema::hasTable('system_observability_events'))return null;
            $category=Str::limit(Str::lower($category),40,'');$severity=in_array($severity,['info','warning','error','critical'],true)?$severity:'info';
            $eventType=Str::limit($eventType,100,'');$source=$source?Str::limit($source,180,''):null;$safe=$this->sanitize($context);
            $normalized=preg_replace('/\b\d+\b/','#',Str::lower(Str::limit($message,1000,'')))?:$message;
            $fingerprint=$fingerprint?:hash('sha256',implode('|',[$category,$eventType,$source??'',$workspaceId??0,$normalized]));
            $cutoff=now()->subMinutes((int)config('workintel.observability.dedupe_minutes',15));
            $existing=SystemObservabilityEvent::query()->where('fingerprint',$fingerprint)->whereNull('resolved_at')->where('last_seen_at','>=',$cutoff)->latest('last_seen_at')->first();
            if($existing){
                $existing->occurrence_count=(int)$existing->occurrence_count+1;$existing->last_seen_at=now();
                if($durationMs!==null)$existing->duration_ms=max((float)$existing->duration_ms,(float)$durationMs);
                $existing->context=$safe;$existing->save();return $existing->fresh();
            }
            return SystemObservabilityEvent::query()->create([
                'uuid'=>(string)Str::uuid(),'workspace_id'=>$workspaceId,'category'=>$category,'severity'=>$severity,'event_type'=>$eventType,'source'=>$source,
                'fingerprint'=>$fingerprint,'message'=>Str::limit($message,4000,''),'context'=>$safe,'duration_ms'=>$durationMs,'occurrence_count'=>1,'first_seen_at'=>now(),'last_seen_at'=>now(),
            ]);
        }catch(Throwable){return null;}finally{self::$recording=false;}
    }

    /** Capture an application exception without leaking request secrets or causing recursive failures. */
    public function recordException(Throwable $exception,?Request $request=null): void
    {
        $request??=app()->bound('request')?request():null;$workspaceId=$request?->attributes->get('workspace')?->id;
        $context=['exception_class'=>$exception::class,'code'=>$exception->getCode(),'file'=>basename($exception->getFile()),'line'=>$exception->getLine()];
        if($request){$context+=['method'=>$request->method(),'path'=>$request->path(),'route'=>$request->route()?->getName()??$request->route()?->uri(),'user_id'=>$request->user()?->id];}
        $this->record('runtime','runtime.exception',$exception::class.': '.$exception->getMessage(),'critical',$context,$workspaceId,'exception');
    }

    /** Capture slow or failed HTTP requests after a response has been produced. */
    public function recordRequest(Request $request,mixed $response,float $durationMs): void
    {
        $status=method_exists($response,'getStatusCode')?$response->getStatusCode():200;$threshold=(float)config('workintel.observability.slow_request_ms',1200);
        if($status<500&&$durationMs<$threshold)return;
        $type=$status>=500?'request.failed':'request.slow';$severity=$status>=500?'error':'warning';$workspaceId=$request->attributes->get('workspace')?->id;
        $this->record('request',$type,$request->method().' '.$request->path().' returned '.$status,$severity,[
            'method'=>$request->method(),'path'=>$request->path(),'route'=>$request->route()?->getName()??$request->route()?->uri(),'status'=>$status,'user_id'=>$request->user()?->id,
        ],$workspaceId,'http',$durationMs);
    }

    /** Capture a slow query without recording bindings or database credentials. */
    public function recordQuery(QueryExecuted $query): void
    {
        if((float)$query->time<(float)config('workintel.observability.slow_query_ms',350))return;
        if(str_contains(Str::lower($query->sql),'system_observability_'))return;
        $this->record('query','query.slow','Slow database query detected.','warning',[
            'sql'=>Str::limit($query->sql,1800,''),'connection'=>$query->connectionName,
        ],null,'database',(float)$query->time,hash('sha256','query|'.$query->connectionName.'|'.$query->sql));
    }

    /** Upsert one subsystem heartbeat used for scheduler and queue freshness checks. */
    public function heartbeat(string $key,int $expectedIntervalSeconds=60,array $metadata=[]): ?SystemObservabilityHeartbeat
    {
        if(self::$recording)return null;self::$recording=true;
        try{
            if(!Schema::hasTable('system_observability_heartbeats'))return null;
            return SystemObservabilityHeartbeat::query()->updateOrCreate(['key'=>Str::limit($key,80,'')],[
                'status'=>'healthy','expected_interval_seconds'=>max(10,$expectedIntervalSeconds),'last_seen_at'=>now(),'metadata'=>$this->sanitize($metadata),
            ]);
        }catch(Throwable){return null;}finally{self::$recording=false;}
    }

    /** Create default platform alert rules only when the operator has not already customized them. */
    public function ensureDefaultRules(): void
    {
        if(!Schema::hasTable('system_observability_alert_rules'))return;
        foreach($this->defaultRules() as $rule)SystemObservabilityAlertRule::query()->firstOrCreate(['key'=>$rule['key']],$rule);
    }

    /** Return built-in alert thresholds suitable for a new installation. */
    public function defaultRules(): array
    {
        return [
            ['key'=>'runtime-errors','name'=>'Runtime errors','metric_key'=>'error_events_15m','operator'=>'>=','threshold'=>5,'window_minutes'=>15,'severity'=>'critical','enabled'=>true,'cooldown_minutes'=>30,'channels'=>['dashboard']],
            ['key'=>'failed-jobs','name'=>'Failed queue jobs','metric_key'=>'failed_jobs_60m','operator'=>'>=','threshold'=>1,'window_minutes'=>60,'severity'=>'critical','enabled'=>true,'cooldown_minutes'=>30,'channels'=>['dashboard']],
            ['key'=>'slow-requests','name'=>'Slow requests','metric_key'=>'slow_requests_15m','operator'=>'>=','threshold'=>10,'window_minutes'=>15,'severity'=>'warning','enabled'=>true,'cooldown_minutes'=>30,'channels'=>['dashboard']],
            ['key'=>'failed-webhooks','name'=>'Failed webhooks','metric_key'=>'failed_webhooks_60m','operator'=>'>=','threshold'=>5,'window_minutes'=>60,'severity'=>'warning','enabled'=>true,'cooldown_minutes'=>60,'channels'=>['dashboard']],
            ['key'=>'payment-failures','name'=>'Payment failures','metric_key'=>'payment_failures_60m','operator'=>'>=','threshold'=>3,'window_minutes'=>60,'severity'=>'critical','enabled'=>true,'cooldown_minutes'=>60,'channels'=>['dashboard']],
            ['key'=>'storage-failures','name'=>'Storage failures','metric_key'=>'storage_failures_60m','operator'=>'>=','threshold'=>3,'window_minutes'=>60,'severity'=>'warning','enabled'=>true,'cooldown_minutes'=>60,'channels'=>['dashboard']],
            ['key'=>'scheduler-stale','name'=>'Scheduler heartbeat stale','metric_key'=>'scheduler_age_seconds','operator'=>'>=','threshold'=>180,'window_minutes'=>5,'severity'=>'critical','enabled'=>true,'cooldown_minutes'=>15,'channels'=>['dashboard']],
        ];
    }

    /** Return current platform observability metrics from both the event ledger and existing domain failure tables. */
    public function metrics(): array
    {
        $now=now();$metrics=[];
        $metrics['error_events_15m']=$this->eventCount(['error','critical'],15);
        $metrics['slow_requests_15m']=$this->typedEventCount('request.slow',15);
        $metrics['slow_queries_15m']=$this->typedEventCount('query.slow',15);
        $metrics['failed_jobs_60m']=$this->tableCount('failed_jobs','failed_at',60);
        $metrics['failed_webhooks_60m']=$this->modelCount(WebhookDelivery::class,'failed_at',60,['status'=>'failed']);
        $metrics['payment_failures_60m']=$this->paymentFailures(60);
        $metrics['storage_failures_60m']=$this->modelCount(ScreenshotStorageJob::class,'updated_at',60,['status'=>['failed','dead']]);
        $scheduler=Schema::hasTable('system_observability_heartbeats')?SystemObservabilityHeartbeat::query()->where('key','scheduler')->first():null;
        $metrics['scheduler_age_seconds']=$scheduler?max(0,$scheduler->last_seen_at->diffInSeconds($now)):999999;
        $latestVerified=Schema::hasTable('system_backup_runs')?SystemBackupRun::query()->whereNotNull('verified_at')->latest('verified_at')->first():null;
        $metrics['backup_age_hours']=$latestVerified?round($latestVerified->verified_at->diffInMinutes($now)/60,2):999999;
        return $metrics;
    }

    /** Evaluate alert rules, opening new incidents or auto-resolving recovered metric alerts. */
    public function evaluateAlerts(): array
    {
        if(!Schema::hasTable('system_observability_alert_rules')||!Schema::hasTable('system_observability_alerts'))return ['triggered'=>0,'resolved'=>0];
        $this->ensureDefaultRules();$metrics=$this->metrics();$triggered=0;$resolved=0;
        foreach(SystemObservabilityAlertRule::query()->where('enabled',true)->get() as $rule){
            $value=(float)($metrics[$rule->metric_key]??0);$breached=$this->compare($value,$rule->operator,(float)$rule->threshold);
            $open=SystemObservabilityAlert::query()->where('alert_rule_id',$rule->id)->whereIn('status',['open','acknowledged'])->latest()->first();
            if($breached){
                $cooldownOk=!$rule->last_triggered_at||$rule->last_triggered_at->lte(now()->subMinutes((int)$rule->cooldown_minutes));
                if(!$open&&$cooldownOk){$alert=$this->openAlert($rule,$value);$rule->update(['last_triggered_at'=>now()]);$this->notifyOperators($alert,$rule);$triggered++;}
            }elseif($open){$open->update(['status'=>'resolved','resolved_at'=>now(),'context'=>array_merge($open->context??[],['auto_resolved'=>true,'recovered_value'=>$value])]);$resolved++;}
        }
        return ['triggered'=>$triggered,'resolved'=>$resolved,'metrics'=>$metrics];
    }

    /** Acknowledge an open alert without resolving the underlying health condition. */
    public function acknowledge(SystemObservabilityAlert $alert,User $actor): SystemObservabilityAlert
    {
        abort_unless($alert->status==='open',422,'Only open alerts can be acknowledged.');$alert->update(['status'=>'acknowledged','acknowledged_at'=>now(),'acknowledged_by'=>$actor->id]);return $alert->fresh('rule');
    }

    /** Resolve an open or acknowledged alert after an operator has verified recovery. */
    public function resolve(SystemObservabilityAlert $alert,User $actor): SystemObservabilityAlert
    {
        abort_unless(in_array($alert->status,['open','acknowledged'],true),422,'Alert is already resolved.');$alert->update(['status'=>'resolved','resolved_at'=>now(),'resolved_by'=>$actor->id]);return $alert->fresh('rule');
    }

    /** Return dashboard-ready health, recent events, alert rules, incidents and domain failure counts. */
    public function overview(): array
    {
        $this->ensureDefaultRules();$metrics=$this->metrics();
        return [
            'summary'=>[
                'open_alerts'=>Schema::hasTable('system_observability_alerts')?SystemObservabilityAlert::query()->whereIn('status',['open','acknowledged'])->count():0,
                'critical_alerts'=>Schema::hasTable('system_observability_alerts')?SystemObservabilityAlert::query()->whereIn('status',['open','acknowledged'])->where('severity','critical')->count():0,
                'errors_15m'=>$metrics['error_events_15m'],'slow_requests_15m'=>$metrics['slow_requests_15m'],'failed_jobs_60m'=>$metrics['failed_jobs_60m'],
                'failed_webhooks_60m'=>$metrics['failed_webhooks_60m'],'payment_failures_60m'=>$metrics['payment_failures_60m'],'storage_failures_60m'=>$metrics['storage_failures_60m'],
            ],
            'metrics'=>$metrics,
            'heartbeats'=>Schema::hasTable('system_observability_heartbeats')?SystemObservabilityHeartbeat::query()->orderBy('key')->get():collect(),
            'events'=>Schema::hasTable('system_observability_events')?SystemObservabilityEvent::query()->with('workspace:id,name')->latest('last_seen_at')->limit(250)->get():collect(),
            'alerts'=>Schema::hasTable('system_observability_alerts')?SystemObservabilityAlert::query()->with('rule:id,key,name,metric_key')->latest('triggered_at')->limit(150)->get():collect(),
            'rules'=>Schema::hasTable('system_observability_alert_rules')?SystemObservabilityAlertRule::query()->orderBy('name')->get():collect(),
            'failed_jobs'=>$this->failedJobs(),
        ];
    }

    /** Update one existing alert rule while preserving its immutable metric key. */
    public function updateRule(SystemObservabilityAlertRule $rule,array $data,User $actor): SystemObservabilityAlertRule
    {
        $rule->fill($data+['updated_by'=>$actor->id])->save();return $rule->fresh();
    }

    /** Prune old resolved events and alerts while preserving active incidents and rules. */
    public function prune(): array
    {
        $days=max(7,(int)config('workintel.observability.retention_days',30));$cutoff=now()->subDays($days);
        $events=Schema::hasTable('system_observability_events')?SystemObservabilityEvent::query()->where('last_seen_at','<',$cutoff)->whereNotNull('resolved_at')->delete():0;
        $alerts=Schema::hasTable('system_observability_alerts')?SystemObservabilityAlert::query()->where('status','resolved')->where('resolved_at','<',$cutoff)->delete():0;
        return ['events'=>$events,'alerts'=>$alerts,'retention_days'=>$days];
    }

    /** Return sanitized failed-job metadata without exposing serialized job payloads or exception bodies. */
    public function failedJobs(): array
    {
        if(!Schema::hasTable('failed_jobs'))return [];
        return DB::table('failed_jobs')->select(['id','uuid','connection','queue','failed_at'])->orderByDesc('failed_at')->limit(100)->get()->map(fn($row)=>(array)$row)->all();
    }

    /** Sanitize arbitrary context recursively so secrets and bearer tokens are never persisted. */
    public function sanitize(array $context): array
    {
        $walk=function(mixed $value,string $key='')use(&$walk):mixed{
            if(preg_match('/password|secret|token|authorization|cookie|credential|private[_-]?key|api[_-]?key/i',$key))return '[REDACTED]';
            if(is_array($value)){foreach($value as $k=>$v)$value[$k]=$walk($v,(string)$k);return $value;}
            if(is_string($value)){$value=preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i','Bearer [REDACTED]',$value)??$value;return Str::limit($value,4000,'');}
            if(is_object($value))return '[OBJECT '.get_class($value).']';
            return $value;
        };
        return $walk($context);
    }

    /** Count recent observability events at selected severity levels. */
    private function eventCount(array $severities,int $minutes): int
    {
        if(!Schema::hasTable('system_observability_events'))return 0;return (int)SystemObservabilityEvent::query()->whereIn('severity',$severities)->where('last_seen_at','>=',now()->subMinutes($minutes))->sum('occurrence_count');
    }

    /** Count recent event occurrences for one event type. */
    private function typedEventCount(string $type,int $minutes): int
    {
        if(!Schema::hasTable('system_observability_events'))return 0;return (int)SystemObservabilityEvent::query()->where('event_type',$type)->where('last_seen_at','>=',now()->subMinutes($minutes))->sum('occurrence_count');
    }

    /** Count recent rows in an existing table using a timestamp field. */
    private function tableCount(string $table,string $column,int $minutes): int
    {
        if(!Schema::hasTable($table))return 0;return DB::table($table)->where($column,'>=',now()->subMinutes($minutes))->count();
    }

    /** Count recent failed rows for an Eloquent model with simple status criteria. */
    private function modelCount(string $model,string $column,int $minutes,array $criteria): int
    {
        try{$query=$model::query()->where($column,'>=',now()->subMinutes($minutes));foreach($criteria as $field=>$value)is_array($value)?$query->whereIn($field,$value):$query->where($field,$value);return $query->count();}catch(Throwable){return 0;}
    }

    /** Count payment and refund failures across platform and workspace client commerce. */
    private function paymentFailures(int $minutes): int
    {
        $since=now()->subMinutes($minutes);$count=0;
        try{if(Schema::hasTable('billing_transactions'))$count+=BillingTransaction::query()->where('status','failed')->where('updated_at','>=',$since)->count();}catch(Throwable){}
        try{if(Schema::hasTable('commerce_refunds'))$count+=CommerceRefund::query()->where('status','failed')->where('updated_at','>=',$since)->count();}catch(Throwable){}
        try{if(Schema::hasTable('client_payment_checkout_sessions'))$count+=ClientPaymentCheckoutSession::query()->where('status','failed')->where('failed_at','>=',$since)->count();}catch(Throwable){}
        return $count;
    }

    /** Compare a metric to a configured threshold using an allowlisted operator. */
    private function compare(float $value,string $operator,float $threshold): bool
    {
        return match($operator){'>'=>$value>$threshold,'>='=>$value>=$threshold,'<' =>$value<$threshold,'<='=>$value<=$threshold,'=='=>abs($value-$threshold)<0.0001,default=>false};
    }

    /** Create one alert incident from a breached rule. */
    private function openAlert(SystemObservabilityAlertRule $rule,float $value): SystemObservabilityAlert
    {
        return SystemObservabilityAlert::query()->create(['uuid'=>(string)Str::uuid(),'alert_rule_id'=>$rule->id,'status'=>'open','severity'=>$rule->severity,'title'=>$rule->name,'message'=>$rule->name.' threshold breached: '.$value.' '.$rule->operator.' '.$rule->threshold.'.','metric_value'=>$value,'threshold'=>$rule->threshold,'context'=>['metric_key'=>$rule->metric_key,'window_minutes'=>$rule->window_minutes],'triggered_at'=>now()]);
    }

    /** Send optional platform alert email without allowing mail failures to recurse into the evaluator. */
    private function notifyOperators(SystemObservabilityAlert $alert,SystemObservabilityAlertRule $rule): void
    {
        if(!(bool)config('workintel.observability.email_alerts',false)||!in_array('email',$rule->channels??[],true))return;
        foreach(config('workintel.commerce.operator_emails',[]) as $email){try{Notification::route('mail',$email)->notify(new PlatformObservabilityAlertMail($alert));}catch(Throwable $e){$this->record('mail','mail.observability_alert_failed','Platform observability alert email failed.','warning',['exception'=>$e->getMessage(),'recipient_hash'=>hash('sha256',Str::lower($email))],null,'mail');}}
    }
}
