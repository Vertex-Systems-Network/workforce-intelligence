<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Provides leave type behavior within the WorkIntel application. */ class LeaveType extends Model
{
    protected $fillable = ['workspace_id', 'name', 'code', 'is_paid', 'annual_allowance_days', 'status'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['is_paid' => 'boolean', 'annual_allowance_days' => 'decimal:2']; }
    /** Handles the requests operation for the current WorkIntel workflow. */ public function requests(): HasMany { return $this->hasMany(LeaveRequest::class); }
    /** Handles the policy operation for the current WorkIntel workflow. */ public function policy(): HasOne { return $this->hasOne(LeavePolicy::class); }
    /** Handles the balances operation for the current WorkIntel workflow. */ public function balances(): HasMany { return $this->hasMany(LeaveBalance::class); }
}
