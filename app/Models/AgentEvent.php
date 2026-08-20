<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides agent event behavior within the WorkIntel application. */ class AgentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['event_uuid', 'workspace_id', 'device_id', 'member_id', 'event_type', 'occurred_at', 'payload', 'received_at'];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'payload' => 'array', 'received_at' => 'datetime'];
    }

    /** Handles the device operation for the current WorkIntel workflow. */ public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
}
