<?php

namespace App\Models;

use App\Casts\DateOnly;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides task recurrence behavior within the WorkIntel application. */ class TaskRecurrence extends Model
{
    protected $fillable = ['workspace_id', 'task_id', 'frequency', 'interval', 'starts_on', 'ends_on', 'next_run_at', 'last_generated_at', 'active'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['starts_on' => DateOnly::class, 'ends_on' => DateOnly::class, 'next_run_at' => 'datetime', 'last_generated_at' => 'datetime', 'active' => 'boolean']; }
    /** Handles the task operation for the current WorkIntel workflow. */ public function task(): BelongsTo { return $this->belongsTo(Task::class); }
}
