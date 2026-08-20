<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides browser access token behavior within the WorkIntel application. */ class BrowserAccessToken extends Model
{
    public $timestamps = false;
    protected $fillable = ['browser_connection_id', 'token_hash', 'last_used_at', 'expires_at', 'revoked_at', 'created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['last_used_at'=>'datetime','expires_at'=>'datetime','revoked_at'=>'datetime','created_at'=>'datetime']; }
    /** Handles the connection operation for the current WorkIntel workflow. */ public function connection(): BelongsTo { return $this->belongsTo(BrowserConnection::class, 'browser_connection_id'); }
}
