<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides time session event behavior within the WorkIntel application. */ class TimeSessionEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['time_session_id', 'event_type', 'occurred_at', 'metadata'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['occurred_at' => 'datetime', 'metadata' => 'array']; }
    /** Handles the session operation for the current WorkIntel workflow. */ public function session(): BelongsTo { return $this->belongsTo(TimeSession::class, 'time_session_id'); }
}
