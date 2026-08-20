<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides application session behavior within the WorkIntel application. */ class ApplicationSession extends Model
{
    protected $fillable = [
        'session_uuid', 'workspace_id', 'member_id', 'device_id', 'project_id', 'task_id', 'app_key', 'app_name',
        'process_name', 'window_title', 'started_at', 'ended_at', 'duration_seconds', 'active_seconds', 'idle_seconds', 'source',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['started_at' => 'datetime', 'ended_at' => 'datetime']; }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the device operation for the current WorkIntel workflow. */ public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    /** Handles the project operation for the current WorkIntel workflow. */ public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    /** Handles the task operation for the current WorkIntel workflow. */ public function task(): BelongsTo { return $this->belongsTo(Task::class); }
}
