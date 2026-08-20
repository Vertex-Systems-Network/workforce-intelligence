<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides task checklist item behavior within the WorkIntel application. */ class TaskChecklistItem extends Model
{
    protected $fillable = ['workspace_id', 'task_id', 'title', 'sort_order', 'is_completed', 'assignee_member_id', 'due_at', 'completed_by_member_id', 'completed_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['sort_order' => 'integer', 'is_completed' => 'boolean', 'due_at' => 'datetime', 'completed_at' => 'datetime']; }
    /** Handles the task operation for the current WorkIntel workflow. */ public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    /** Handles the assignee operation for the current WorkIntel workflow. */ public function assignee(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'assignee_member_id'); }
    /** Handles the completed by operation for the current WorkIntel workflow. */ public function completedBy(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'completed_by_member_id'); }
}
