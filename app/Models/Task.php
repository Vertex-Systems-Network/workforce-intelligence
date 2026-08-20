<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Provides task behavior within the WorkIntel application. */ class Task extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'workspace_id', 'project_id', 'parent_id', 'recurrence_template_id', 'task_status_id', 'owner_member_id',
        'title', 'description', 'description_html', 'status', 'priority', 'estimated_minutes', 'start_at', 'due_at',
        'position', 'billable', 'client_visible', 'created_by', 'completed_at',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'position' => 'integer',
            'billable' => 'boolean',
            'client_visible' => 'boolean',
        ];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the project operation for the current WorkIntel workflow. */ public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    /** Handles the parent operation for the current WorkIntel workflow. */ public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    /** Handles the subtasks operation for the current WorkIntel workflow. */ public function subtasks(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id'); }
    /** Handles the workflow status operation for the current WorkIntel workflow. */ public function workflowStatus(): BelongsTo { return $this->belongsTo(TaskStatus::class, 'task_status_id'); }
    /** Handles the owner operation for the current WorkIntel workflow. */ public function owner(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'owner_member_id'); }
    /** Handles the comments operation for the current WorkIntel workflow. */ public function comments(): HasMany { return $this->hasMany(TaskComment::class)->latest('id'); }
    /** Handles the attachments operation for the current WorkIntel workflow. */ public function attachments(): HasMany { return $this->hasMany(TaskAttachment::class)->latest('id'); }
    /** Handles the dependencies operation for the current WorkIntel workflow. */ public function dependencies(): HasMany { return $this->hasMany(TaskDependency::class); }
    /** Handles the dependents operation for the current WorkIntel workflow. */ public function dependents(): HasMany { return $this->hasMany(TaskDependency::class, 'depends_on_task_id'); }
    /** Handles the recurrence operation for the current WorkIntel workflow. */ public function recurrence(): HasOne { return $this->hasOne(TaskRecurrence::class); }
    /** Handles the recurrence template operation for the current WorkIntel workflow. */ public function recurrenceTemplate(): BelongsTo { return $this->belongsTo(self::class, 'recurrence_template_id'); }
    /** Handles the recurrence instances operation for the current WorkIntel workflow. */ public function recurrenceInstances(): HasMany { return $this->hasMany(self::class, 'recurrence_template_id'); }
    /** Handles the checklist items operation for the current WorkIntel workflow. */ public function checklistItems(): HasMany { return $this->hasMany(TaskChecklistItem::class)->orderBy('sort_order')->orderBy('id'); }
    /** Handles the activities operation for the current WorkIntel workflow. */ public function activities(): HasMany { return $this->hasMany(TaskActivity::class)->latest('id'); }
    /** Handles the relations operation for the current WorkIntel workflow. */ public function relations(): HasMany { return $this->hasMany(TaskRelation::class, 'source_task_id'); }
    /** Handles the inverse relations operation for the current WorkIntel workflow. */ public function inverseRelations(): HasMany { return $this->hasMany(TaskRelation::class, 'target_task_id'); }

    /** Handles the time entries operation for the current WorkIntel workflow. */ public function timeEntries(): HasMany { return $this->hasMany(TimeEntry::class); }

    /** Handles the assignees operation for the current WorkIntel workflow. */ public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceMember::class, 'task_assignees', 'task_id', 'member_id')->withTimestamps();
    }

    /** Handles the observers operation for the current WorkIntel workflow. */ public function observers(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceMember::class, 'task_observers', 'task_id', 'member_id')->withTimestamps();
    }

    /** Handles the tags operation for the current WorkIntel workflow. */ public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TaskTag::class, 'task_tag_assignments', 'task_id', 'tag_id')->withTimestamps();
    }
}
