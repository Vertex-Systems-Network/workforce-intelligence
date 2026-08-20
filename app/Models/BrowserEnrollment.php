<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Provides browser enrollment behavior within the WorkIntel application. */ class BrowserEnrollment extends Model
{
    protected $fillable = ['workspace_id', 'member_id', 'created_by', 'code_hash', 'expires_at', 'used_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['expires_at' => 'datetime', 'used_at' => 'datetime']; }
}
