<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides commerce checkout session behavior within the WorkIntel application. */ class CommerceCheckoutSession extends Model
{
    protected $fillable=['uuid','workspace_id','user_id','subscription_plan_id','commerce_coupon_id','billing_interval','provider','status','seat_quantity','currency','subtotal','discount_total','tax_total','total','provider_session_id','checkout_url','expires_at','completed_at','canceled_at','metadata'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['subtotal'=>'decimal:2','discount_total'=>'decimal:2','tax_total'=>'decimal:2','total'=>'decimal:2','expires_at'=>'datetime','completed_at'=>'datetime','canceled_at'=>'datetime','metadata'=>'array'];}
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace():BelongsTo{return $this->belongsTo(Workspace::class);}/** Handles the plan operation for the current WorkIntel workflow. */ public function plan():BelongsTo{return $this->belongsTo(SubscriptionPlan::class,'subscription_plan_id');}/** Handles the coupon operation for the current WorkIntel workflow. */ public function coupon():BelongsTo{return $this->belongsTo(CommerceCoupon::class,'commerce_coupon_id');}
}
