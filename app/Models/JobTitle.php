<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides job title behavior within the WorkIntel application. */ class JobTitle extends Model
{
    protected $fillable = ['workspace_id', 'name', 'code', 'description', 'status'];

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the members operation for the current WorkIntel workflow. */ public function members(): HasMany { return $this->hasMany(WorkspaceMember::class); }
}
