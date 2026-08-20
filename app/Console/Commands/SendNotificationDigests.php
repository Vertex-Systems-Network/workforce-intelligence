<?php
namespace App\Console\Commands;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceNotification;
use App\Notifications\WorkspaceDigestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
/** Provides send notification digests behavior within the WorkIntel application. */ class SendNotificationDigests extends Command
{
    protected $signature='workintel:send-notification-digests {frequency : daily|weekly}';
    protected $description='Send pending notification email digests.';
    /** Executes the command, job, or request handler. */ public function handle(): int
    {
        if (! Schema::hasTable('notification_preferences') || ! Schema::hasTable('workspace_notifications')) { $this->warn('Notification tables are not installed; skipping digest run.'); return self::SUCCESS; }
        $frequency=(string)$this->argument('frequency');if(!in_array($frequency,['daily','weekly'],true)){$this->error('Use daily or weekly.');return self::FAILURE;}
        $sent=0;NotificationPreference::where('email',true)->where('digest',$frequency)->get()->groupBy(fn($p)=>$p->workspace_id.':'.$p->user_id)->each(function($prefs)use($frequency,&$sent){$first=$prefs->first();$user=User::find($first->user_id);$workspace=Workspace::find($first->workspace_id);if(!$user||!$workspace)return;$categories=$prefs->pluck('category');$items=WorkspaceNotification::where('workspace_id',$workspace->id)->where('user_id',$user->id)->whereIn('category',$categories)->whereNull('email_sent_at')->orderBy('created_at')->limit(200)->get();if($items->isEmpty())return;try{$locale=\App\Support\LocaleCatalog::normalize($workspace->preferences?->default_language?:$user->preferredLocale());$user->notify((new WorkspaceDigestMail($workspace->name,$frequency,$items->map(fn($x)=>['title'=>$x->title,'body'=>$x->body])->all()))->locale($locale));WorkspaceNotification::whereKey($items->pluck('id'))->update(['email_sent_at'=>now()]);$sent++;}catch(\Throwable $e){report($e);}});$this->info("Digests sent: {$sent}");return self::SUCCESS;
    }
}
