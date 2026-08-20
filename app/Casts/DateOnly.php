<?php

namespace App\Casts;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Persist SQL DATE columns as YYYY-MM-DD on every database driver.
 *
 * Laravel's built-in `date` cast serializes values for persistence using the
 * connection datetime format. MySQL DATE columns silently truncate that value,
 * while SQLite keeps the full `YYYY-MM-DD 00:00:00` string. Exact lookups and
 * unique constraints can therefore behave differently in tests vs production.
 */
/** Provides date only behavior within the WorkIntel application. */ final class DateOnly implements CastsAttributes
{
    /** Returns get data required by the current workflow. */ public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->startOfDay();
    }

    /** Handles the set operation for the current WorkIntel workflow. */ public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        return Carbon::parse($value)->toDateString();
    }
}
