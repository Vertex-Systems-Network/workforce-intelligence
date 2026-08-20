<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides agent sync batch behavior within the WorkIntel application. */ class AgentSyncBatch extends Model
{
    public $timestamps = false;

    protected $fillable = ['batch_uuid', 'workspace_id', 'device_id', 'event_count', 'accepted_count', 'duplicate_count', 'client_created_at', 'received_at'];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return ['client_created_at' => 'datetime', 'received_at' => 'datetime'];
    }

    /** Handles the device operation for the current WorkIntel workflow. */ public function device(): BelongsTo { return $this->belongsTo(Device::class); }
}
