<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides task relation behavior within the WorkIntel application. */ class TaskRelation extends Model
{
    protected $fillable = ['workspace_id', 'source_task_id', 'target_task_id', 'type'];
    /** Handles the source task operation for the current WorkIntel workflow. */ public function sourceTask(): BelongsTo { return $this->belongsTo(Task::class, 'source_task_id'); }
    /** Handles the target task operation for the current WorkIntel workflow. */ public function targetTask(): BelongsTo { return $this->belongsTo(Task::class, 'target_task_id'); }
}
