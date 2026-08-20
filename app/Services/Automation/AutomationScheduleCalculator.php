<?php

namespace App\Services\Automation;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/** Provides automation schedule calculator behavior within the WorkIntel application. */ class AutomationScheduleCalculator
{
    /** Handles the next operation for the current WorkIntel workflow. */ public function next(array $config, string $timezone, ?CarbonInterface $after = null): CarbonImmutable
    {
        $frequency = $config['frequency'] ?? 'daily';
        $local = CarbonImmutable::instance(($after ?? now())->copy())
            ->setTimezone($timezone)
            ->addMinute()
            ->startOfMinute();

        if ($frequency === 'every_15_minutes') {
            $remainder = $local->minute % 15;
            return ($remainder === 0 ? $local : $local->addMinutes(15 - $remainder))->utc();
        }

        [$hour, $minute] = $this->time($config['at'] ?? '09:00');

        if ($frequency === 'hourly') {
            $minute = isset($config['minute']) ? max(0, min(59, (int) $config['minute'])) : $minute;
            $candidate = $local->startOfHour()->minute($minute);
            if ($candidate->lessThanOrEqualTo($local)) $candidate = $candidate->addHour();
            return $candidate->utc();
        }

        if ($frequency === 'daily') {
            $candidate = $local->setTime($hour, $minute);
            if ($candidate->lessThanOrEqualTo($local)) $candidate = $candidate->addDay();
            return $candidate->utc();
        }

        if ($frequency === 'weekly') {
            $weekday = $this->weekday($config['weekday'] ?? 1);
            $candidate = $local->setTime($hour, $minute);
            if ($local->dayOfWeek !== $weekday || $candidate->lessThanOrEqualTo($local)) {
                $daysAhead = ($weekday - $local->dayOfWeek + 7) % 7;
                if ($daysAhead === 0) $daysAhead = 7;
                $candidate = $local->addDays($daysAhead)->setTime($hour, $minute);
            }
            return $candidate->utc();
        }

        if ($frequency === 'monthly') {
            $day = max(1, min(28, (int) ($config['day'] ?? 1)));
            $candidate = $local->day($day)->setTime($hour, $minute);
            if ($candidate->lessThanOrEqualTo($local)) {
                $next = $local->addMonthNoOverflow()->startOfMonth();
                $candidate = $next->day($day)->setTime($hour, $minute);
            }
            return $candidate->utc();
        }

        throw ValidationException::withMessages(['trigger_config.frequency' => ['Unsupported schedule frequency.']]);
    }

    /** Handles the weekday operation for the current WorkIntel workflow. */ private function weekday(mixed $value): int
    {
        if (is_string($value) && ! is_numeric($value)) {
            $map = ['sunday'=>0, 'monday'=>1, 'tuesday'=>2, 'wednesday'=>3, 'thursday'=>4, 'friday'=>5, 'saturday'=>6];
            $key = strtolower(trim($value));
            if (! array_key_exists($key, $map)) {
                throw ValidationException::withMessages(['trigger_config.weekday' => ['Choose a valid weekday.']]);
            }
            return $map[$key];
        }
        return max(0, min(6, (int) $value));
    }

    /** Handles the time operation for the current WorkIntel workflow. */ private function time(string $value): array
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $value, $match)) {
            throw ValidationException::withMessages(['trigger_config.at' => ['Use HH:MM time format.']]);
        }
        $hour = (int) $match[1];
        $minute = (int) $match[2];
        if ($hour > 23 || $minute > 59) {
            throw ValidationException::withMessages(['trigger_config.at' => ['Use a valid 24-hour time.']]);
        }
        return [$hour, $minute];
    }
}
