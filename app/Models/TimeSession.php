<?php

namespace App\Models;

use App\Enums\TimeSource;
use App\Enums\TimerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides time session behavior within the WorkIntel application. */ class TimeSession extends Model
{
    protected $fillable = [
        'uuid', 'workspace_id', 'member_id', 'project_id', 'task_id', 'started_at', 'stopped_at',
        'status', 'source', 'billable', 'note',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'stopped_at' => 'datetime', 'status' => TimerStatus::class,
            'source' => TimeSource::class, 'billable' => 'boolean',
        ];
    }

    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the project operation for the current WorkIntel workflow. */ public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    /** Handles the task operation for the current WorkIntel workflow. */ public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    /** Handles the events operation for the current WorkIntel workflow. */ public function events(): HasMany { return $this->hasMany(TimeSessionEvent::class); }
}
