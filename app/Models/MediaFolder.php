<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Represents a workspace media-library folder with optional nesting. */
class MediaFolder extends Model
{
    use SoftDeletes;

    protected $fillable = ['workspace_id', 'parent_id', 'name', 'slug', 'sort_order', 'created_by'];

    /** Defines model casting for predictable ordering. */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** Returns the workspace that owns this media folder. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Returns the parent folder when this folder is nested. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    /** Returns child folders ordered for predictable library navigation. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /** Returns active media assets stored inside this folder. */
    public function assets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'folder_id');
    }
}
