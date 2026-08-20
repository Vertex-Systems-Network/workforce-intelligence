<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides browser connection behavior within the WorkIntel application. */ class BrowserConnection extends Model
{
    protected $fillable = [
        'uuid', 'workspace_id', 'member_id', 'device_id', 'installation_id', 'browser_name', 'browser_version',
        'extension_version', 'status', 'enrolled_at', 'last_seen_at', 'last_sync_at', 'last_ip', 'revoked_at',
    ];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['enrolled_at'=>'datetime','last_seen_at'=>'datetime','last_sync_at'=>'datetime','revoked_at'=>'datetime']; }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the tokens operation for the current WorkIntel workflow. */ public function tokens(): HasMany { return $this->hasMany(BrowserAccessToken::class); }
}
