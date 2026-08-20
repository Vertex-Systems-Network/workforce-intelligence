<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores one member-specific Media Library favorite without changing asset permissions. */
class MediaFavorite extends Model
{
    protected $fillable = ['workspace_id', 'workspace_member_id', 'media_asset_id'];
    /** Returns the workspace that owns this preference. */
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Returns the member who favorited the asset. */
    public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'workspace_member_id'); }
    /** Returns the favorited media asset. */
    public function asset(): BelongsTo { return $this->belongsTo(MediaAsset::class, 'media_asset_id')->withTrashed(); }
}
