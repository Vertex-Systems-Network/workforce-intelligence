<?php
namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Notifications\WorkspaceAlertMail;

/** Provides workspace notification service behavior within the WorkIntel application. */ class WorkspaceNotificationService
{
    private const DEFAULT_CATEGORIES=['attendance','payroll','agents','reports','security','clients','approvals','automations','chat','chat_mentions','chat_threads','chat_direct','chat_channels','workspace'];

    /** Handles the defaults operation for the current WorkIntel workflow. */ public function defaults(int $workspaceId,int $userId): array
    {
        if (! Schema::hasTable('notification_preferences')) return [];
        return collect(self::DEFAULT_CATEGORIES)->map(function(string $category) use($workspaceId,$userId){
            return NotificationPreference::firstOrCreate(['workspace_id'=>$workspaceId,'user_id'=>$userId,'category'=>$category],['in_app'=>true,'email'=>false,'digest'=>'immediate']);
        })->all();
    }

    /** Sends notify information to the configured recipient. */ public function notify(Workspace $workspace,User $user,string $category,string $type,string $title,?string $body=null,string $severity='info',array $data=[]): ?WorkspaceNotification
    {
        if (! Schema::hasTable('notification_preferences') || ! Schema::hasTable('workspace_notifications')) return null;
        $pref=NotificationPreference::firstOrCreate(['workspace_id'=>$workspace->id,'user_id'=>$user->id,'category'=>$category],['in_app'=>true,'email'=>false,'digest'=>'immediate']);
        $notification=null;
        if($pref->in_app || $pref->email){
            $notification=WorkspaceNotification::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'user_id'=>$user->id,'category'=>$category,'type'=>$type,'severity'=>$severity,'title'=>$title,'body'=>$body,'data'=>$data,'created_at'=>now()]);
        }
        if($pref->email && $pref->digest==='immediate' && $notification){
            try{$locale=\App\Support\LocaleCatalog::normalize($workspace->preferences?->default_language?:$user->preferredLocale());$user->notify((new WorkspaceAlertMail($title,$body,$workspace->name))->locale($locale));$notification->update(['email_sent_at'=>now()]);}catch(\Throwable $e){report($e);try{app(\App\Services\Observability\ObservabilityService::class)->record('mail','mail.delivery_failed','Workspace notification email failed.','warning',['notification_type'=>$type,'category'=>$category,'exception_class'=>$e::class],$workspace->id,'mail');}catch(\Throwable){}}
        }
        return $notification;
    }

    /** Handles the from audit operation for the current WorkIntel workflow. */ public function fromAudit(Workspace $workspace,?User $actor,string $action,array $data): void
    {
        $mapping=[
            'payroll.approved'=>['payroll','Payroll approved','A payroll run was approved and locked.','success'],
            'payroll.paid'=>['payroll','Payroll marked paid','A payroll run was marked as paid.','success'],
            'report.generated'=>['reports','Report generated','A workforce report snapshot was generated.','info'],
            'device.revoked'=>['agents','Device revoked','A desktop agent device was revoked.','warning'],
            'client_invoice.sent'=>['clients','Client invoice sent','A client invoice was issued.','info'],
            'client_invoice.payment_recorded'=>['clients','Client payment recorded','A client invoice payment was recorded.','success'],
        ];
        if(!isset($mapping[$action])) return;
        [$category,$title,$body,$severity]=$mapping[$action];
        $users=$workspace->members()->with(['user','roles'])->where('status','active')->get()->filter(fn($member)=>$member->user&&($member->roles->contains('slug','owner')||$member->roles->contains('slug','admin')||$member->user_id===$actor?->id))->pluck('user')->unique('id');
        foreach($users as $user) $this->notify($workspace,$user,$category,$action,$title,$body,$severity,$data);
    }
}
