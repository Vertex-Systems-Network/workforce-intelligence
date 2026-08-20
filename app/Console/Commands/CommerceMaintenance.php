<?php
namespace App\Console\Commands;
use App\Models\CommerceCheckoutSession;use App\Models\CommerceDunningAttempt;use Illuminate\Console\Command;
/** Provides commerce maintenance behavior within the WorkIntel application. */ class CommerceMaintenance extends Command
{
    protected $signature='workintel:commerce-maintenance';protected $description='Expire stale commerce checkouts and advance dunning reminders.';
    /** Executes the command, job, or request handler. */ public function handle():int{$expired=CommerceCheckoutSession::whereIn('status',['pending','redirect'])->where('expires_at','<',now())->update(['status'=>'expired']);$attempted=0;$max=max(1,(int)config('workintel.commerce.dunning_max_attempts',4));CommerceDunningAttempt::where('status','scheduled')->where('next_attempt_at','<=',now())->orderBy('id')->chunkById(100,function($rows)use(&$attempted,$max){foreach($rows as $row){$row->update(['status'=>'attempted','attempted_at'=>now()]);$attempted++;if($row->attempt_number<$max)CommerceDunningAttempt::firstOrCreate(['workspace_subscription_id'=>$row->workspace_subscription_id,'attempt_number'=>$row->attempt_number+1],['billing_invoice_id'=>$row->billing_invoice_id,'status'=>'scheduled','next_attempt_at'=>now()->addDays(min(7,($row->attempt_number+1)*2))]);}});$this->info("Expired {$expired} checkout(s); advanced {$attempted} dunning attempt(s).");return self::SUCCESS;}
}
