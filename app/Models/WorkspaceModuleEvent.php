<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides workspace module event behavior within the WorkIntel application. */ class WorkspaceModuleEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'module_key', 'actor_member_id', 'action', 'before_state', 'after_state', 'metadata', 'created_at',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array', 'metadata' => 'array', 'created_at' => 'datetime'];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the actor operation for the current WorkIntel workflow. */ public function actor(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'actor_member_id'); }
}
