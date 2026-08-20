<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides agent command behavior within the WorkIntel application. */ class AgentCommand extends Model
{
    protected $fillable = ['uuid', 'workspace_id', 'device_id', 'queued_by', 'command_type', 'payload', 'status', 'delivered_at', 'acknowledged_at', 'result'];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return ['payload' => 'array', 'result' => 'array', 'delivered_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    /** Handles the device operation for the current WorkIntel workflow. */ public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    /** Handles the queued by operation for the current WorkIntel workflow. */ public function queuedBy(): BelongsTo { return $this->belongsTo(User::class, 'queued_by'); }
}
