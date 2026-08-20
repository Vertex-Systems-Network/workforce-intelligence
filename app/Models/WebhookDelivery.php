<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides webhook delivery behavior within the WorkIntel application. */ class WebhookDelivery extends Model
{
    public $timestamps=false;
    protected $fillable=['uuid','workspace_id','webhook_endpoint_id','event_type','event_id','payload','status','attempts','last_status_code','last_response_excerpt','next_attempt_at','delivered_at','failed_at','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['payload'=>'array','next_attempt_at'=>'datetime','delivered_at'=>'datetime','failed_at'=>'datetime','created_at'=>'datetime']; }
    /** Handles the endpoint operation for the current WorkIntel workflow. */ public function endpoint(): BelongsTo { return $this->belongsTo(WebhookEndpoint::class,'webhook_endpoint_id'); }
}
