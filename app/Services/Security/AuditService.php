<?php

namespace App\Services\Security;

use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Integrations\WebhookService;
use App\Services\Notifications\WorkspaceNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Provides audit service behavior within the WorkIntel application. */ class AuditService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(
        private readonly WebhookService $webhooks,
        private readonly WorkspaceNotificationService $notifications,
    ) {}

    /** Handles the record workspace request operation for the current WorkIntel workflow. */ public function recordWorkspaceRequest(Request $request, int $statusCode): ?AuditLog
    {
        /** @var Workspace|null $workspace */
        $workspace=$request->attributes->get('workspace');
        if(!$workspace || ! Schema::hasTable('audit_logs')) return null;
        /** @var WorkspaceMember|null $member */
        $member=$request->attributes->get('workspaceMember');
        $action=$this->eventName($request);
        $category=$this->category($action);
        $metadata=[
            'input'=>$this->sanitize($request->except(['password','password_confirmation','token','secret','access_token','api_key'])),
        ];
        $log=AuditLog::create([
            'uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'user_id'=>$request->user()?->id,'member_id'=>$member?->id,
            'actor_type'=>'user','actor_id'=>$request->user()?->id ? (string)$request->user()->id : null,
            'action'=>$action,'category'=>$category,'method'=>$request->method(),'path'=>'/'.$request->path(),
            'route_name'=>$request->route()?->getName(),'status_code'=>$statusCode,'ip_address'=>$request->ip(),
            'user_agent'=>Str::limit((string)$request->userAgent(),500,''),'metadata'=>$metadata,
            'risk_level'=>$this->riskLevel($request,$statusCode),'created_at'=>now(),
        ]);
        if($statusCode>=200 && $statusCode<400){
            $payload=['audit_id'=>$log->uuid,'action'=>$action,'category'=>$category,'actor'=>['user_id'=>$request->user()?->id,'member_id'=>$member?->id],'status_code'=>$statusCode,'input'=>$metadata['input'],'occurred_at'=>$log->created_at->toIso8601String()];
            if($action==='approvals.updated'){
                $routeApproval=$request->route('approvalRequest');$approvalId=$routeApproval instanceof ApprovalRequest?$routeApproval->id:(is_numeric($routeApproval)?(int)$routeApproval:null);
                if($approvalId){$approval=ApprovalRequest::find($approvalId);if($approval)$payload['approval']=['id'=>$approval->id,'uuid'=>$approval->uuid,'subject_type'=>$approval->subject_type,'subject_id'=>$approval->subject_id,'status'=>$approval->status,'amount'=>$approval->amount,'currency'=>$approval->currency,'title'=>$approval->title];}
            }
            $this->webhooks->queueEvent($workspace,$action,$payload);
            $this->notifications->fromAudit($workspace,$request->user(),$action,$payload);
        }
        return $log;
    }

    /** Handles the record api key request operation for the current WorkIntel workflow. */ public function recordApiKeyRequest(Request $request, int $statusCode): ?AuditLog
    {
        if (! Schema::hasTable('audit_logs')) return null;
        $workspace=$request->attributes->get('workspace');$key=$request->attributes->get('workspaceApiKey');
        return AuditLog::create([
            'uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace?->id,'actor_type'=>'api_key','actor_id'=>$key?->uuid,
            'action'=>'api.'.strtolower($request->method()),'category'=>'api','method'=>$request->method(),'path'=>'/'.$request->path(),
            'route_name'=>$request->route()?->getName(),'status_code'=>$statusCode,'ip_address'=>$request->ip(),'user_agent'=>Str::limit((string)$request->userAgent(),500,''),
            'metadata'=>['api_key_name'=>$key?->name,'scopes'=>$key?->scopes],'risk_level'=>$statusCode>=400?'elevated':'normal','created_at'=>now(),
        ]);
    }

    /** Handles the event name operation for the current WorkIntel workflow. */ public function eventName(Request $request): string
    {
        $path=$request->path();$method=$request->method();
        $map=[
            ['timer/start','POST','time.started'],['timer/','POST',$path&&str_ends_with($path,'/stop')?'time.stopped':(str_ends_with($path,'/pause')?'time.paused':(str_ends_with($path,'/resume')?'time.resumed':null))],
            ['attendance/clock-in','POST','attendance.clocked_in'],['attendance/clock-out','POST','attendance.clocked_out'],
            ['payroll/runs/','POST',str_ends_with($path,'/approve')?'payroll.approved':(str_ends_with($path,'/mark-paid')?'payroll.paid':null)],
            ['reports/','POST',str_contains($path,'/run')||str_ends_with($path,'/runs')?'report.generated':null],
            ['devices/','POST',str_ends_with($path,'/revoke')?'device.revoked':null],
            ['client-invoices/','POST',str_ends_with($path,'/send')?'client_invoice.sent':(str_ends_with($path,'/payments')?'client_invoice.payment_recorded':null)],
            ['approvals/','POST',str_ends_with($path,'/decision')?'approvals.updated':null],
        ];
        foreach($map as [$needle,$verb,$event]) if($event&&$method===$verb&&str_contains($path,$needle)) return $event;
        $resource=collect(explode('/',$path))->last(fn($segment)=>$segment!==''&&!is_numeric($segment)) ?: 'workspace';
        $verb=match($method){'POST'=>'created','PUT','PATCH'=>'updated','DELETE'=>'deleted',default=>strtolower($method)};
        return Str::of($resource)->replace('-','_')->snake().'.'.$verb;
    }

    /** Handles the category operation for the current WorkIntel workflow. */ private function category(string $action): string
    {
        return match(true){
            str_starts_with($action,'payroll.')=>'payroll',str_starts_with($action,'attendance.')=>'attendance',str_starts_with($action,'device.')=>'agents',
            str_starts_with($action,'report.')=>'reports',str_starts_with($action,'client_')=>'clients',str_contains($action,'api')||str_contains($action,'webhook')=>'security',default=>'workspace'};
    }

    /** Handles the risk level operation for the current WorkIntel workflow. */ private function riskLevel(Request $request,int $status): string
    {
        if($status>=400) return 'elevated';
        if($request->isMethod('DELETE')||str_contains($request->path(),'revoke')||str_contains($request->path(),'approve')) return 'elevated';
        return 'normal';
    }

    /** Handles the sanitize operation for the current WorkIntel workflow. */ private function sanitize(mixed $value,int $depth=0): mixed
    {
        if($depth>3) return '[truncated]';
        if(is_array($value)){
            $out=[];
            foreach(array_slice($value,0,30,true) as $key=>$item){
                if(is_string($key)&&preg_match('/password|secret|token|key|authorization|cookie|credential/i',$key)){$out[$key]='[redacted]';continue;}
                $out[$key]=$this->sanitize($item,$depth+1);
            }
            return $out;
        }
        if($value instanceof \Illuminate\Http\UploadedFile) return ['file'=>$value->getClientOriginalName(),'size'=>$value->getSize(),'mime'=>$value->getClientMimeType()];
        if(is_object($value)) return '[object '.get_class($value).']';
        if(is_string($value)) return Str::limit($value,500,'…');
        return $value;
    }
}
