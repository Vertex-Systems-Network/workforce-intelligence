<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides intelligence run behavior within the WorkIntel application. */ class IntelligenceRun extends Model
{
    protected $fillable = ['uuid','workspace_id','trigger','status','initiated_by','started_at','completed_at','stats','error'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['started_at'=>'datetime','completed_at'=>'datetime','stats'=>'array']; }
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the insights operation for the current WorkIntel workflow. */ public function insights(): HasMany { return $this->hasMany(IntelligenceInsight::class); }
}
