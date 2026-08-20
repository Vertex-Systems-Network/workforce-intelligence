<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Represents a verified workspace hostname and the platform purpose assigned to it. */
class WorkspaceDomain extends Model
{
    protected $fillable = ['uuid','workspace_id','purpose','hostname','status','verification_nonce','verification_method','verified_at','activated_at','certificate_status','last_checked_at','last_error'];

    /** Defines date casting for verification and certificate health timestamps. */
    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'activated_at' => 'datetime', 'last_checked_at' => 'datetime'];
    }

    /** Returns the workspace that owns this hostname. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Returns the Website Studio site currently assigned to this hostname. */
    public function websiteSite(): HasOne
    {
        return $this->hasOne(WebsiteSite::class, 'custom_domain_id');
    }
}
