<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides approval request behavior within the WorkIntel application. */ class ApprovalRequest extends Model
{
    protected $fillable = ['uuid','workspace_id','approval_workflow_id','trigger_key','subject_type','subject_id','requester_member_id','title','summary','status','current_step_position','amount','currency','submitted_at','due_at','completed_at','context','metadata'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['amount'=>'decimal:2','submitted_at'=>'datetime','due_at'=>'datetime','completed_at'=>'datetime','context'=>'array','metadata'=>'array','current_step_position'=>'integer']; }
    /** Handles the workflow operation for the current WorkIntel workflow. */ public function workflow(): BelongsTo { return $this->belongsTo(ApprovalWorkflow::class,'approval_workflow_id'); }
    /** Handles the requester operation for the current WorkIntel workflow. */ public function requester(): BelongsTo { return $this->belongsTo(WorkspaceMember::class,'requester_member_id'); }
    /** Handles the steps operation for the current WorkIntel workflow. */ public function steps(): HasMany { return $this->hasMany(ApprovalRequestStep::class)->orderBy('position'); }
    /** Handles the decisions operation for the current WorkIntel workflow. */ public function decisions(): HasMany { return $this->hasMany(ApprovalDecision::class)->orderBy('acted_at'); }
    /** Handles the current step operation for the current WorkIntel workflow. */ public function currentStep(): ?ApprovalRequestStep { return $this->steps->firstWhere('position',(int)$this->current_step_position); }
}
