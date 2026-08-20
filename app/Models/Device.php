<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides device behavior within the WorkIntel application. */ class Device extends Model
{
    protected $fillable = [
        'uuid', 'workspace_id', 'member_id', 'installation_id', 'name', 'platform', 'os_name', 'os_version',
        'architecture', 'agent_version', 'machine_fingerprint_hash', 'status', 'tracking_status', 'is_idle',
        'offline_queue_size', 'capabilities', 'metadata', 'last_ip', 'enrolled_at', 'last_heartbeat_at',
        'last_seen_at', 'last_sync_at', 'revoked_at',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'is_idle' => 'boolean', 'capabilities' => 'array', 'metadata' => 'array', 'enrolled_at' => 'datetime',
            'last_heartbeat_at' => 'datetime', 'last_seen_at' => 'datetime', 'last_sync_at' => 'datetime', 'revoked_at' => 'datetime',
        ];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the tokens operation for the current WorkIntel workflow. */ public function tokens(): HasMany { return $this->hasMany(DeviceAccessToken::class); }
    /** Handles the events operation for the current WorkIntel workflow. */ public function events(): HasMany { return $this->hasMany(AgentEvent::class); }
    /** Synchronizes sync batches data with the current application state. */ public function syncBatches(): HasMany { return $this->hasMany(AgentSyncBatch::class); }
    /** Handles the commands operation for the current WorkIntel workflow. */ public function commands(): HasMany { return $this->hasMany(AgentCommand::class); }
}
