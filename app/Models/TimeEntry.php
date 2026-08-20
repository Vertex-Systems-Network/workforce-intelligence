<?php

namespace App\Models;

use App\Casts\DateOnly;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides time entry behavior within the WorkIntel application. */ class TimeEntry extends Model
{
    protected $fillable = [
        'workspace_id', 'member_id', 'project_id', 'task_id', 'time_session_id', 'date', 'started_at', 'ended_at',
        'duration_seconds', 'billable', 'source', 'approval_status', 'note',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return ['date' => DateOnly::class, 'started_at' => 'datetime', 'ended_at' => 'datetime', 'billable' => 'boolean'];
    }

    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the project operation for the current WorkIntel workflow. */ public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    /** Handles the task operation for the current WorkIntel workflow. */ public function task(): BelongsTo { return $this->belongsTo(Task::class); }
}
