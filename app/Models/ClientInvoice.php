<?php
namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** Provides client invoice behavior within the WorkIntel application. */ class ClientInvoice extends Model
{
    protected $fillable=['uuid','workspace_id','client_id','created_by','number','status','currency','issue_date','due_date','period_start','period_end','subtotal','discount_total','tax_percent','tax_total','total','amount_paid','amount_due','notes','terms','allowed_gateways','invoice_schedule_id','sent_at','paid_at','voided_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['issue_date'=>DateOnly::class,'due_date'=>DateOnly::class,'period_start'=>DateOnly::class,'period_end'=>DateOnly::class,'subtotal'=>'decimal:2','discount_total'=>'decimal:2','tax_percent'=>'decimal:4','tax_total'=>'decimal:2','allowed_gateways'=>'array','total'=>'decimal:2','amount_paid'=>'decimal:2','amount_due'=>'decimal:2','sent_at'=>'datetime','paid_at'=>'datetime','voided_at'=>'datetime']; }
    /** Returns the workspace that owns the invoice. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the client operation for the current WorkIntel workflow. */ public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    /** Handles the lines operation for the current WorkIntel workflow. */ public function lines(): HasMany { return $this->hasMany(ClientInvoiceLine::class); }
    /** Handles the payments operation for the current WorkIntel workflow. */ public function payments(): HasMany { return $this->hasMany(ClientPayment::class); }
    /** Handles the time entries operation for the current WorkIntel workflow. */ public function timeEntries(): BelongsToMany { return $this->belongsToMany(TimeEntry::class,'client_invoice_time_entries')->withPivot(['hours','rate','amount'])->withTimestamps(); }
}
