<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Provides tracking exclusion behavior within the WorkIntel application. */ class TrackingExclusion extends Model
{
    protected $fillable = ['workspace_id', 'scope_type', 'scope_id', 'target_type', 'pattern', 'reason', 'active', 'created_by'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['active' => 'boolean']; }
}
