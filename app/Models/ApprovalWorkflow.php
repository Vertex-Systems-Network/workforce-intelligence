<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides approval workflow behavior within the WorkIntel application. */ class ApprovalWorkflow extends Model
{
    protected $fillable = ['uuid','workspace_id','name','trigger_key','system_key','description','status','priority','conditions','sla_hours','escalation_role_slug','notify_requester','created_by','updated_by'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['conditions'=>'array','notify_requester'=>'boolean','priority'=>'integer','sla_hours'=>'integer']; }
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the steps operation for the current WorkIntel workflow. */ public function steps(): HasMany { return $this->hasMany(ApprovalWorkflowStep::class)->orderBy('position'); }
    /** Handles the requests operation for the current WorkIntel workflow. */ public function requests(): HasMany { return $this->hasMany(ApprovalRequest::class); }
}
