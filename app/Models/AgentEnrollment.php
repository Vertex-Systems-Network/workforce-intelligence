<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides agent enrollment behavior within the WorkIntel application. */ class AgentEnrollment extends Model
{
    protected $fillable = ['workspace_id', 'member_id', 'created_by', 'code_hash', 'expires_at', 'used_at', 'browser_used_at'];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'browser_used_at' => 'datetime'];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the creator operation for the current WorkIntel workflow. */ public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
