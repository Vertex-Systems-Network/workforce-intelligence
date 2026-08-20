<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides task dependency behavior within the WorkIntel application. */ class TaskDependency extends Model
{
    protected $fillable = ['workspace_id', 'task_id', 'depends_on_task_id', 'type'];

    /** Handles the task operation for the current WorkIntel workflow. */ public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    /** Handles the depends on task operation for the current WorkIntel workflow. */ public function dependsOnTask(): BelongsTo { return $this->belongsTo(Task::class, 'depends_on_task_id'); }
}
