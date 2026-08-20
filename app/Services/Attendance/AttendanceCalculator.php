<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use Carbon\Carbon;

/** Provides attendance calculator behavior within the WorkIntel application. */ class AttendanceCalculator
{
    /** Handles the recalculate operation for the current WorkIntel workflow. */ public function recalculate(AttendanceRecord $record): AttendanceRecord
    {
        $record->loadMissing(['shiftAssignment.shift', 'breaks']);
        $unpaidBreakSeconds = (int) $record->breaks->where('paid', false)->sum('duration_seconds');
        $worked = 0;
        if ($record->clock_in_at && $record->clock_out_at) {
            $worked = max(0, (int) $record->clock_in_at->diffInSeconds($record->clock_out_at) - $unpaidBreakSeconds);
        }

        $late = 0;
        $overtime = 0;
        $shift = $record->shiftAssignment?->shift;
        if ($shift && $record->clock_in_at) {
            $scheduledStart = Carbon::parse($record->date->toDateString().' '.$shift->start_time, $record->clock_in_at->timezone);
            $late = max(0, (int) $scheduledStart->copy()->addMinutes($shift->grace_minutes)->diffInMinutes($record->clock_in_at, false));
        }
        if ($shift && $record->clock_out_at) {
            $scheduledEnd = Carbon::parse($record->date->toDateString().' '.$shift->end_time, $record->clock_out_at->timezone);
            if ($scheduledEnd->lessThanOrEqualTo(Carbon::parse($record->date->toDateString().' '.$shift->start_time, $record->clock_out_at->timezone))) {
                $scheduledEnd->addDay();
            }
            $overtime = max(0, (int) $scheduledEnd->diffInMinutes($record->clock_out_at, false));
        }

        $status = $record->status;
        if ($record->flag_type === 'missing_clock_out' && ! $record->clock_out_at) {
            $status = 'missing_clock_out';
        } elseif ($late > 0 && $status === 'present') {
            $status = 'late';
        } elseif ($status === 'missing_clock_out' && $record->clock_out_at) {
            $status = $late > 0 ? 'late' : 'present';
        }

        $record->forceFill([
            'break_seconds' => $unpaidBreakSeconds,
            'worked_seconds' => $worked,
            'late_minutes' => $late,
            'overtime_minutes' => $overtime,
            'status' => $status,
        ])->save();

        return $record->fresh()->load('breaks');
    }
}
