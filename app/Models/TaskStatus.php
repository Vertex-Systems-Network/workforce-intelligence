<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides task status behavior within the WorkIntel application. */ class TaskStatus extends Model
{
    protected $fillable = ['workspace_id', 'name', 'slug', 'color', 'group', 'sort_order', 'is_default', 'is_completed', 'is_archived'];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_default' => 'boolean', 'is_completed' => 'boolean', 'is_archived' => 'boolean'];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the tasks operation for the current WorkIntel workflow. */ public function tasks(): HasMany { return $this->hasMany(Task::class, 'task_status_id'); }
    /** Handles the outgoing transitions operation for the current WorkIntel workflow. */ public function outgoingTransitions(): HasMany { return $this->hasMany(TaskStatusTransition::class, 'from_status_id'); }
    /** Handles the incoming transitions operation for the current WorkIntel workflow. */ public function incomingTransitions(): HasMany { return $this->hasMany(TaskStatusTransition::class, 'to_status_id'); }
}
