<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Provides attendance policy behavior within the WorkIntel application. */ class AttendancePolicy extends Model
{
    protected $fillable = [
        'workspace_id','allow_web','allow_mobile','require_geolocation','require_geofence',
        'max_accuracy_meters','correction_window_days','missed_clock_out_hours',
        'auto_flag_missed_clock_out','allow_employee_corrections',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'allow_web'=>'boolean','allow_mobile'=>'boolean','require_geolocation'=>'boolean','require_geofence'=>'boolean',
            'auto_flag_missed_clock_out'=>'boolean','allow_employee_corrections'=>'boolean',
            'max_accuracy_meters'=>'integer','correction_window_days'=>'integer','missed_clock_out_hours'=>'integer',
        ];
    }
}
