<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents a reusable Document Studio block group owned by one workspace. */
class DocumentComponent extends Model
{
    protected $fillable = ['workspace_id', 'name', 'category', 'content_schema', 'settings', 'version', 'created_by', 'updated_by'];

    /** Defines typed component content and settings. */
    protected function casts(): array
    {
        return ['content_schema' => 'array', 'settings' => 'array'];
    }

    /** Returns the workspace that owns the reusable component. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
