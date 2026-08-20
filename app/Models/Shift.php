<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides shift behavior within the WorkIntel application. */ class Shift extends Model
{
    protected $fillable = ['workspace_id', 'name', 'start_time', 'end_time', 'break_minutes', 'grace_minutes', 'location_type', 'timezone', 'status'];
    /** Handles the assignments operation for the current WorkIntel workflow. */ public function assignments(): HasMany { return $this->hasMany(ShiftAssignment::class); }
}
