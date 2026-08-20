<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides task attachment behavior within the WorkIntel application. */ class TaskAttachment extends Model
{
    protected $fillable = ['task_id', 'member_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes'];
    /** Handles the task operation for the current WorkIntel workflow. */ public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
}
