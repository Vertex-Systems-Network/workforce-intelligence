<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides worker presence behavior within the WorkIntel application. */ class WorkerPresence extends Model
{
    protected $fillable = [
        'workspace_id', 'member_id', 'device_id', 'project_id', 'task_id', 'status', 'tracking_status',
        'app_name', 'domain', 'activity_percent', 'timer_started_at', 'status_since', 'last_seen_at', 'metadata',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'activity_percent' => 'integer',
            'timer_started_at' => 'datetime',
            'status_since' => 'datetime',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the device operation for the current WorkIntel workflow. */ public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    /** Handles the project operation for the current WorkIntel workflow. */ public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    /** Handles the task operation for the current WorkIntel workflow. */ public function task(): BelongsTo { return $this->belongsTo(Task::class); }
}
