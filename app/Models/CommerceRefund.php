<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides commerce refund behavior within the WorkIntel application. */ class CommerceRefund extends Model
{
    protected $fillable=['uuid','workspace_id','billing_invoice_id','billing_transaction_id','provider','status','currency','amount','provider_refund_id','reason','failure_message','requested_by','processed_at','metadata'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['amount'=>'decimal:2','processed_at'=>'datetime','metadata'=>'array'];}
}
