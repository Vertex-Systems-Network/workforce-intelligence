<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides task status transition behavior within the WorkIntel application. */ class TaskStatusTransition extends Model
{
    protected $fillable = ['workspace_id', 'from_status_id', 'to_status_id', 'require_comment'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['require_comment' => 'boolean']; }
    /** Handles the from status operation for the current WorkIntel workflow. */ public function fromStatus(): BelongsTo { return $this->belongsTo(TaskStatus::class, 'from_status_id'); }
    /** Handles the to status operation for the current WorkIntel workflow. */ public function toStatus(): BelongsTo { return $this->belongsTo(TaskStatus::class, 'to_status_id'); }
}
