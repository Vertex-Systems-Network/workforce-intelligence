<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides approval request step behavior within the WorkIntel application. */ class ApprovalRequestStep extends Model
{
    protected $fillable = ['approval_request_id','workflow_step_id','position','name','approver_type','assigned_member_ids','status','required_approvals','approved_count','allow_self_approval','due_at','completed_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['assigned_member_ids'=>'array','position'=>'integer','required_approvals'=>'integer','approved_count'=>'integer','allow_self_approval'=>'boolean','due_at'=>'datetime','completed_at'=>'datetime']; }
    /** Handles the request operation for the current WorkIntel workflow. */ public function request(): BelongsTo { return $this->belongsTo(ApprovalRequest::class,'approval_request_id'); }
    /** Handles the workflow step operation for the current WorkIntel workflow. */ public function workflowStep(): BelongsTo { return $this->belongsTo(ApprovalWorkflowStep::class,'workflow_step_id'); }
}
