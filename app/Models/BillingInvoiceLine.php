<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides billing invoice line behavior within the WorkIntel application. */ class BillingInvoiceLine extends Model
{
    protected $fillable=['billing_invoice_id','description','quantity','unit_amount','amount','metadata'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['quantity'=>'decimal:2','unit_amount'=>'decimal:2','amount'=>'decimal:2','metadata'=>'array']; }
    /** Handles the invoice operation for the current WorkIntel workflow. */ public function invoice(): BelongsTo { return $this->belongsTo(BillingInvoice::class,'billing_invoice_id'); }
}
