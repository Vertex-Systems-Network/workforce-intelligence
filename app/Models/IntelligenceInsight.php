<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides intelligence insight behavior within the WorkIntel application. */ class IntelligenceInsight extends Model
{
    protected $fillable = [
        'uuid','workspace_id','intelligence_run_id','fingerprint','category','insight_type','scope_type','scope_id','scope_label',
        'severity','title','summary','explanation','metrics','source_refs','recommendations','status','auto_resolve',
        'detected_at','last_detected_at','acknowledged_at','acknowledged_by','resolved_at','resolved_by','resolution_note',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'metrics'=>'array','source_refs'=>'array','recommendations'=>'array','auto_resolve'=>'boolean',
            'detected_at'=>'datetime','last_detected_at'=>'datetime','acknowledged_at'=>'datetime','resolved_at'=>'datetime',
        ];
    }

    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): BelongsTo { return $this->belongsTo(IntelligenceRun::class, 'intelligence_run_id'); }
}
