<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides approval workflow step behavior within the WorkIntel application. */ class ApprovalWorkflowStep extends Model
{
    protected $fillable = ['approval_workflow_id','position','name','approver_type','approver_role_slug','approver_member_id','required_approvals','allow_self_approval'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['position'=>'integer','required_approvals'=>'integer','allow_self_approval'=>'boolean']; }
    /** Handles the workflow operation for the current WorkIntel workflow. */ public function workflow(): BelongsTo { return $this->belongsTo(ApprovalWorkflow::class,'approval_workflow_id'); }
    /** Handles the approver member operation for the current WorkIntel workflow. */ public function approverMember(): BelongsTo { return $this->belongsTo(WorkspaceMember::class,'approver_member_id'); }
}
