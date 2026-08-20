<?php
namespace App\Services\Commerce;
use App\Models\{BillingInvoice,BillingTransaction,CommerceCheckoutSession,CommerceRefund,Workspace,WorkspaceSubscription};
/** Provides seller analytics service behavior within the WorkIntel application. */ class SellerAnalyticsService
{
    /** Handles the summary operation for the current WorkIntel workflow. */ public function summary():array
    {
        $subs=WorkspaceSubscription::with('plan')->whereIn('status',['active','trialing','past_due'])->get();$mrr=0.0;
        foreach($subs as $s){if(!$s->plan||$s->plan->slug==='free')continue;$monthly=$s->billing_interval==='annual'?((float)$s->plan->annual_price_per_seat/12):(float)$s->plan->monthly_price_per_seat;$mrr+=$monthly*max(1,$s->seat_quantity);}
        $monthStart=now()->startOfMonth();$paid=(float)BillingTransaction::where('type','payment')->where('status','succeeded')->where('processed_at','>=',$monthStart)->sum('amount');$refunded=(float)CommerceRefund::where('status','succeeded')->where('processed_at','>=',$monthStart)->sum('amount');$canceled=WorkspaceSubscription::whereNotNull('canceled_at')->where('canceled_at','>=',$monthStart)->count();$denom=max(1,$subs->count()+$canceled);
        return ['customers'=>Workspace::where('workspace_type','production')->count(),'paid_subscriptions'=>$subs->filter(fn($s)=>$s->plan?->slug!=='free')->count(),'mrr'=>round($mrr,2),'arr'=>round($mrr*12,2),'month_collected'=>round($paid,2),'month_refunded'=>round($refunded,2),'churn_percent'=>round($canceled/$denom*100,2),'open_invoices'=>BillingInvoice::whereIn('status',['open','uncollectible'])->count(),'pending_checkouts'=>CommerceCheckoutSession::whereIn('status',['pending','redirect'])->count()];
    }
}
