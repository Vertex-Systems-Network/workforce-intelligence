<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Provides task tag behavior within the WorkIntel application. */ class TaskTag extends Model
{
    protected $fillable = ['workspace_id', 'name', 'slug', 'color', 'is_archived'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['is_archived' => 'boolean']; }
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the tasks operation for the current WorkIntel workflow. */ public function tasks(): BelongsToMany { return $this->belongsToMany(Task::class, 'task_tag_assignments', 'tag_id', 'task_id')->withTimestamps(); }
}
