<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** Provides workspace subscription behavior within the WorkIntel application. */ class WorkspaceSubscription extends Model
{
    protected $fillable=['uuid','workspace_id','subscription_plan_id','status','billing_interval','provider','provider_customer_id','provider_subscription_id','seat_quantity','trial_started_at','trial_ends_at','current_period_start','current_period_end','cancel_at_period_end','canceled_at','grace_ends_at','ended_at','provider_metadata'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['trial_started_at'=>'datetime','trial_ends_at'=>'datetime','current_period_start'=>'datetime','current_period_end'=>'datetime','cancel_at_period_end'=>'boolean','canceled_at'=>'datetime','grace_ends_at'=>'datetime','ended_at'=>'datetime','provider_metadata'=>'array']; }
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the plan operation for the current WorkIntel workflow. */ public function plan(): BelongsTo { return $this->belongsTo(SubscriptionPlan::class,'subscription_plan_id'); }
    /** Handles the invoices operation for the current WorkIntel workflow. */ public function invoices(): HasMany { return $this->hasMany(BillingInvoice::class); }
    /** Determines whether the is entitled condition is satisfied. */ public function isEntitled(): bool { if(in_array($this->status,['active','trialing'],true)) return true; return $this->status==='past_due' && $this->grace_ends_at?->isFuture(); }
}
