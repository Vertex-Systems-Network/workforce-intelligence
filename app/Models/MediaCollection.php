<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Represents a reusable workspace DAM collection independent from physical folders. */
class MediaCollection extends Model
{
    protected $fillable = ['workspace_id', 'name', 'description', 'visibility', 'created_by'];

    /** Returns the workspace that owns this collection. */
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Returns the user who created this collection. */
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    /** Returns workspace members explicitly allowed to discover a restricted collection. */
    public function members(): BelongsToMany { return $this->belongsToMany(WorkspaceMember::class, 'media_collection_members', 'media_collection_id', 'workspace_member_id')->withPivot('role')->withTimestamps(); }

    /** Returns assets assigned to this collection. */
    public function assets(): BelongsToMany { return $this->belongsToMany(MediaAsset::class, 'media_asset_collection')->withTimestamps(); }
}
