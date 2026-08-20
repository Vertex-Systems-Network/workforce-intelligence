<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides billing transaction behavior within the WorkIntel application. */ class BillingTransaction extends Model
{
    protected $fillable=['uuid','workspace_id','billing_invoice_id','provider','type','status','currency','amount','provider_transaction_id','failure_message','processed_at','metadata'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['amount'=>'decimal:2','processed_at'=>'datetime','metadata'=>'array']; }
    /** Handles the invoice operation for the current WorkIntel workflow. */ public function invoice(): BelongsTo { return $this->belongsTo(BillingInvoice::class,'billing_invoice_id'); }
}
