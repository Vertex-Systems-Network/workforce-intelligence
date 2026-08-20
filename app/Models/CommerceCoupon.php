<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** Provides commerce coupon behavior within the WorkIntel application. */ class CommerceCoupon extends Model
{
    protected $fillable=['uuid','code','name','discount_type','discount_value','currency','eligible_plans','max_redemptions','redeemed_count','active','starts_at','redeem_by','created_by'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['discount_value'=>'decimal:2','eligible_plans'=>'array','active'=>'boolean','starts_at'=>'datetime','redeem_by'=>'datetime'];}
    /** Handles the redemptions operation for the current WorkIntel workflow. */ public function redemptions():HasMany{return $this->hasMany(CommerceCouponRedemption::class);}
}
