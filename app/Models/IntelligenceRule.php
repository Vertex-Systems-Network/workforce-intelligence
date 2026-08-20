<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Provides intelligence rule behavior within the WorkIntel application. */ class IntelligenceRule extends Model
{
    protected $fillable = [
        'workspace_id','rule_key','name','category','status','severity','window_days',
        'threshold_value','threshold_secondary','config','sort_order',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'threshold_value' => 'decimal:3',
            'threshold_secondary' => 'decimal:3',
            'config' => 'array',
        ];
    }
}
