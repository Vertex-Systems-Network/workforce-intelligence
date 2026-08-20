<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Defines a recurring client-invoice template and its next execution time. */
class ClientInvoiceSchedule extends Model
{
    protected $fillable=['uuid','workspace_id','client_id','created_by','name','status','frequency','interval_count','due_days','currency','discount_total','tax_percent','auto_send','include_unbilled_time','project_ids','lines','allowed_gateways','reminder_settings','notes','terms','starts_at','next_run_at','last_run_at','ends_at','paused_at'];
    /** Defines recurring invoice schedule casts. */ protected function casts(): array { return ['discount_total'=>'decimal:2','tax_percent'=>'decimal:4','auto_send'=>'boolean','include_unbilled_time'=>'boolean','project_ids'=>'array','lines'=>'array','allowed_gateways'=>'array','reminder_settings'=>'array','starts_at'=>'datetime','next_run_at'=>'datetime','last_run_at'=>'datetime','ends_at'=>'datetime','paused_at'=>'datetime']; }
    /** Returns the schedule client. */ public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    /** Returns invoices generated from this schedule. */ public function invoices(): HasMany { return $this->hasMany(ClientInvoice::class,'invoice_schedule_id'); }
}
