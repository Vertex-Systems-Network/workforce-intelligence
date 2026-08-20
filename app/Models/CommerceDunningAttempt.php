<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides commerce dunning attempt behavior within the WorkIntel application. */ class CommerceDunningAttempt extends Model
{
    protected $fillable=['workspace_subscription_id','billing_invoice_id','attempt_number','status','attempted_at','next_attempt_at','failure_message'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['attempted_at'=>'datetime','next_attempt_at'=>'datetime'];}
    /** Handles the subscription operation for the current WorkIntel workflow. */ public function subscription():BelongsTo{return $this->belongsTo(WorkspaceSubscription::class,'workspace_subscription_id');}
    /** Handles the invoice operation for the current WorkIntel workflow. */ public function invoice():BelongsTo{return $this->belongsTo(BillingInvoice::class,'billing_invoice_id');}
}
