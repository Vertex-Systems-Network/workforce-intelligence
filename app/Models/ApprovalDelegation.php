<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides approval delegation behavior within the WorkIntel application. */ class ApprovalDelegation extends Model
{
    protected $fillable = ['uuid','workspace_id','delegator_member_id','delegate_member_id','starts_at','ends_at','status','reason','created_by'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['starts_at'=>'datetime','ends_at'=>'datetime']; }
    /** Handles the delegator operation for the current WorkIntel workflow. */ public function delegator(): BelongsTo { return $this->belongsTo(WorkspaceMember::class,'delegator_member_id'); }
    /** Handles the delegate operation for the current WorkIntel workflow. */ public function delegate(): BelongsTo { return $this->belongsTo(WorkspaceMember::class,'delegate_member_id'); }
}
