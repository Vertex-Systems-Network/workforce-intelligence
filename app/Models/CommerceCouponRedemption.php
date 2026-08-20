<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides commerce coupon redemption behavior within the WorkIntel application. */ class CommerceCouponRedemption extends Model{public $timestamps=false;protected $fillable=['commerce_coupon_id','workspace_id','commerce_checkout_session_id','discount_amount','redeemed_at'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['discount_amount'=>'decimal:2','redeemed_at'=>'datetime'];}}
