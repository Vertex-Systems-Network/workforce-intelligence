<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents one deduplicated, privacy-sanitized production observability event. */
class SystemObservabilityEvent extends Model
{
    protected $fillable=['uuid','workspace_id','category','severity','event_type','source','fingerprint','message','context','duration_ms','occurrence_count','first_seen_at','last_seen_at','resolved_at'];

    /** Define encrypted event context and event timestamps. */
    protected function casts(): array
    {
        return ['context'=>'encrypted:array','duration_ms'=>'decimal:3','first_seen_at'=>'datetime','last_seen_at'=>'datetime','resolved_at'=>'datetime'];
    }

    /** Return the workspace associated with this event when one exists. */
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
}
