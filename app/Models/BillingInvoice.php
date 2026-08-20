<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** Provides billing invoice behavior within the WorkIntel application. */ class BillingInvoice extends Model
{
    protected $fillable=['uuid','workspace_id','workspace_subscription_id','number','status','currency','subtotal','tax_total','discount_total','total','amount_paid','amount_due','issued_at','due_at','paid_at','voided_at','provider','provider_invoice_id','provider_hosted_url','metadata'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['subtotal'=>'decimal:2','tax_total'=>'decimal:2','discount_total'=>'decimal:2','total'=>'decimal:2','amount_paid'=>'decimal:2','amount_due'=>'decimal:2','issued_at'=>'datetime','due_at'=>'datetime','paid_at'=>'datetime','voided_at'=>'datetime','metadata'=>'array']; }
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the subscription operation for the current WorkIntel workflow. */ public function subscription(): BelongsTo { return $this->belongsTo(WorkspaceSubscription::class,'workspace_subscription_id'); }
    /** Handles the lines operation for the current WorkIntel workflow. */ public function lines(): HasMany { return $this->hasMany(BillingInvoiceLine::class); }
    /** Handles the transactions operation for the current WorkIntel workflow. */ public function transactions(): HasMany { return $this->hasMany(BillingTransaction::class); }
}
