<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Represents a workspace-scoped tag used to organize media assets. */
class MediaTag extends Model
{
    protected $fillable = ['workspace_id', 'name', 'color'];

    /** Returns the workspace that owns this media tag. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Returns media assets assigned to the tag. */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'media_asset_tag');
    }
}
