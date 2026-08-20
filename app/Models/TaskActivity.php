<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides task activity behavior within the WorkIntel application. */ class TaskActivity extends Model
{
    public $timestamps = false;
    protected $fillable = ['workspace_id', 'task_id', 'actor_member_id', 'action', 'metadata', 'created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['metadata' => 'array', 'created_at' => 'datetime']; }
    /** Handles the task operation for the current WorkIntel workflow. */ public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    /** Handles the actor operation for the current WorkIntel workflow. */ public function actor(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'actor_member_id'); }
}
