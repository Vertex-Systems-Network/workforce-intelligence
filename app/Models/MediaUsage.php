<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Records one workspace resource that currently depends on a media asset. */
class MediaUsage extends Model
{
    protected $fillable = ['workspace_id', 'media_asset_id', 'resource_type', 'resource_id', 'field', 'label', 'created_by'];

    /** Returns the asset referenced by this usage. */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id')->withTrashed();
    }

    /** Returns the workspace that owns the usage. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
