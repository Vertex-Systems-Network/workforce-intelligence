<?php

namespace App\Models;

use App\Casts\DateOnly;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides compensation profile behavior within the WorkIntel application. */ class CompensationProfile extends Model
{
    protected $fillable = [
        'workspace_id', 'member_id', 'pay_type', 'currency', 'hourly_rate', 'daily_rate', 'monthly_salary',
        'annual_salary', 'project_rate', 'premium_hourly_rate', 'standard_hours_per_day', 'standard_hours_per_week',
        'overtime_multiplier', 'weekend_multiplier', 'holiday_multiplier', 'default_tax_percent',
        'deduct_unpaid_leave', 'proration_mode', 'effective_from', 'effective_to', 'status', 'note',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2', 'daily_rate' => 'decimal:2', 'monthly_salary' => 'decimal:2',
            'annual_salary' => 'decimal:2', 'project_rate' => 'decimal:2', 'premium_hourly_rate' => 'decimal:2',
            'standard_hours_per_day' => 'decimal:2', 'standard_hours_per_week' => 'decimal:2',
            'overtime_multiplier' => 'decimal:2', 'weekend_multiplier' => 'decimal:2', 'holiday_multiplier' => 'decimal:2',
            'default_tax_percent' => 'decimal:2', 'deduct_unpaid_leave' => 'boolean', 'effective_from' => DateOnly::class, 'effective_to' => DateOnly::class,
        ];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
}
