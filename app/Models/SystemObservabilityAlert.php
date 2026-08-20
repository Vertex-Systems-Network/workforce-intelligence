<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents one actionable platform observability alert incident. */
class SystemObservabilityAlert extends Model
{
    protected $fillable=['uuid','alert_rule_id','status','severity','title','message','metric_value','threshold','context','triggered_at','acknowledged_at','acknowledged_by','resolved_at','resolved_by'];

    /** Define encrypted alert context, metric and lifecycle timestamp casts. */
    protected function casts(): array
    {
        return ['context'=>'encrypted:array','metric_value'=>'decimal:3','threshold'=>'decimal:3','triggered_at'=>'datetime','acknowledged_at'=>'datetime','resolved_at'=>'datetime'];
    }

    /** Return the rule that generated the alert. */
    public function rule(): BelongsTo { return $this->belongsTo(SystemObservabilityAlertRule::class,'alert_rule_id'); }

    /** Return the user that acknowledged the alert. */
    public function acknowledger(): BelongsTo { return $this->belongsTo(User::class,'acknowledged_by'); }

    /** Return the user that manually resolved the alert. */
    public function resolver(): BelongsTo { return $this->belongsTo(User::class,'resolved_by'); }
}
