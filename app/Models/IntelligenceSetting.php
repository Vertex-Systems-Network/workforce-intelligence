<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Provides intelligence setting behavior within the WorkIntel application. */ class IntelligenceSetting extends Model
{
    protected $fillable = [
        'workspace_id','enabled','run_interval_minutes','forecast_days','default_capacity_hours',
        'automation_events_enabled','snapshot_retention_days',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'automation_events_enabled' => 'boolean',
            'default_capacity_hours' => 'decimal:2',
        ];
    }
}
