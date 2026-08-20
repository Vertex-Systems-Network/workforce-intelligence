<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Represents the most recent health heartbeat from one production subsystem. */
class SystemObservabilityHeartbeat extends Model
{
    protected $fillable=['key','status','expected_interval_seconds','last_seen_at','metadata'];

    /** Define encrypted heartbeat metadata and timestamp casts. */
    protected function casts(): array { return ['last_seen_at'=>'datetime','metadata'=>'encrypted:array']; }
}
