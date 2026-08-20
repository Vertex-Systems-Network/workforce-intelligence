<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides approval decision behavior within the WorkIntel application. */ class ApprovalDecision extends Model
{
    public $timestamps = false;
    protected $fillable = ['workspace_id','approval_request_id','approval_request_step_id','actor_member_id','decision','note','metadata','acted_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['metadata'=>'array','acted_at'=>'datetime']; }
    /** Handles the request operation for the current WorkIntel workflow. */ public function request(): BelongsTo { return $this->belongsTo(ApprovalRequest::class,'approval_request_id'); }
    /** Handles the step operation for the current WorkIntel workflow. */ public function step(): BelongsTo { return $this->belongsTo(ApprovalRequestStep::class,'approval_request_step_id'); }
    /** Handles the actor operation for the current WorkIntel workflow. */ public function actor(): BelongsTo { return $this->belongsTo(WorkspaceMember::class,'actor_member_id'); }
}
