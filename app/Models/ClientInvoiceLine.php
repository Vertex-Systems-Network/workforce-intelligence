<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides client invoice line behavior within the WorkIntel application. */ class ClientInvoiceLine extends Model
{
    protected $fillable=['client_invoice_id','project_id','description','quantity','unit_price','amount','source_type','metadata'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['quantity'=>'decimal:4','unit_price'=>'decimal:2','amount'=>'decimal:2','metadata'=>'array']; }
    /** Handles the invoice operation for the current WorkIntel workflow. */ public function invoice(): BelongsTo { return $this->belongsTo(ClientInvoice::class,'client_invoice_id'); }
    /** Handles the project operation for the current WorkIntel workflow. */ public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}
