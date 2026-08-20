<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents a client-facing hosted or manual invoice-payment checkout attempt. */
class ClientPaymentCheckoutSession extends Model
{
    protected $fillable=['uuid','workspace_id','client_id','client_invoice_id','workspace_client_payment_gateway_id','provider','status','currency','amount','provider_session_id','provider_transaction_id','checkout_url','expires_at','completed_at','failed_at','failure_message','metadata'];
    /** Defines checkout status and monetary casts. */ protected function casts(): array { return ['amount'=>'decimal:2','expires_at'=>'datetime','completed_at'=>'datetime','failed_at'=>'datetime','metadata'=>'array']; }
    /** Returns the invoice being paid. */ public function invoice(): BelongsTo { return $this->belongsTo(ClientInvoice::class,'client_invoice_id'); }
    /** Returns the client paying the invoice. */ public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    /** Returns the workspace gateway used for the checkout. */ public function gateway(): BelongsTo { return $this->belongsTo(WorkspaceClientPaymentGateway::class,'workspace_client_payment_gateway_id'); }
}
