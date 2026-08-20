<?php
namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides client payment behavior within the WorkIntel application. */ class ClientPayment extends Model
{
    protected $fillable=['uuid','workspace_id','client_id','client_invoice_id','recorded_by','amount','currency','method','provider','reference','provider_transaction_id','paid_on','note','metadata'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['amount'=>'decimal:2','paid_on'=>DateOnly::class,'metadata'=>'array']; }
    /** Handles the invoice operation for the current WorkIntel workflow. */ public function invoice(): BelongsTo { return $this->belongsTo(ClientInvoice::class,'client_invoice_id'); }
    /** Handles the client operation for the current WorkIntel workflow. */ public function client(): BelongsTo { return $this->belongsTo(Client::class); }
}
