<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides workspace access session behavior within the WorkIntel application. */ class WorkspaceAccessSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid', 'workspace_id', 'user_id', 'member_id', 'session_hash', 'ip_address', 'user_agent',
        'last_seen_at', 'expires_at', 'revoked_at', 'revoke_reason', 'created_at',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Handles the user operation for the current WorkIntel workflow. */ public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'member_id');
    }
}
