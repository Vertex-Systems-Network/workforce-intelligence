<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Provides productivity rule behavior within the WorkIntel application. */ class ProductivityRule extends Model
{
    protected $fillable = ['workspace_id', 'scope_type', 'scope_id', 'target_type', 'target', 'classification', 'category', 'active', 'created_by'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['active' => 'boolean']; }
}
