<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Provides production health service behavior within the WorkIntel application. */ class ProductionHealthService
{
    /** Handles the readiness operation for the current WorkIntel workflow. */ public function readiness(): array
    {
        $checks = [];
        $criticalFailure = false;

        $this->check($checks, 'database', true, function () {
            DB::select('select 1');
            return 'Database connection healthy.';
        }, $criticalFailure);

        $this->check($checks, 'schema', true, function () {
            if (! Schema::hasTable('migrations')) throw new \RuntimeException('Migration table is missing.');
            $applied = DB::table('migrations')->pluck('migration')->all();
            $pending = [];
            foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                if (! in_array($name, $applied, true)) $pending[] = $name;
            }
            if ($pending) throw new \RuntimeException('Pending migrations: '.implode(', ', array_slice($pending, 0, 5)).(count($pending) > 5 ? '…' : ''));
            $required = \App\Support\ProductionCertificationCatalog::REQUIRED_TABLES;
            $missing = array_values(array_filter($required, fn ($table) => ! Schema::hasTable($table)));
            if ($missing) throw new \RuntimeException('Schema drift; missing table(s): '.implode(', ', $missing));
            return 'All migration files are applied and critical schema landmarks exist.';
        }, $criticalFailure);

        $this->check($checks, 'storage', true, function () {
            $disk = Storage::disk(config('filesystems.default'));
            $path = 'health/.workintel-'.bin2hex(random_bytes(6));
            $disk->put($path, 'ok');
            $ok = $disk->exists($path) && $disk->get($path) === 'ok';
            $disk->delete($path);
            if (! $ok) throw new \RuntimeException('Storage read/write verification failed.');
            return 'Default filesystem disk is readable and writable.';
        }, $criticalFailure);

        $this->check($checks, 'cache', false, function () {
            $key = 'workintel:health:'.bin2hex(random_bytes(5));
            Cache::put($key, 'ok', 30);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);
            if (! $ok) throw new \RuntimeException('Cache read/write verification failed.');
            return 'Cache store is readable and writable.';
        }, $criticalFailure);

        $schedulerHeartbeat = Cache::get('workintel:scheduler-heartbeat');
        $schedulerAge = $schedulerHeartbeat ? now()->diffInSeconds(\Illuminate\Support\Carbon::parse($schedulerHeartbeat)) : null;
        $schedulerRequired = (bool) config('workintel.production.require_scheduler_heartbeat', false);
        $schedulerOk = $schedulerAge !== null && $schedulerAge <= (int) config('workintel.production.scheduler_max_age_seconds', 180);
        $checks['scheduler'] = [
            'ok' => $schedulerOk,
            'critical' => $schedulerRequired,
            'detail' => $schedulerOk ? "Scheduler heartbeat {$schedulerAge}s ago." : 'No recent scheduler heartbeat. Ensure schedule:run executes every minute.',
        ];
        if ($schedulerRequired && ! $schedulerOk) $criticalFailure = true;

        $checks['app_key'] = [
            'ok' => filled(config('app.key')),
            'critical' => true,
            'detail' => filled(config('app.key')) ? 'APP_KEY is configured.' : 'APP_KEY is missing.',
        ];
        if (! filled(config('app.key'))) $criticalFailure = true;

        return [
            'ok' => ! $criticalFailure,
            'environment' => app()->environment(),
            'time' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /** Handles the check operation for the current WorkIntel workflow. */ private function check(array &$checks, string $name, bool $critical, callable $callback, bool &$criticalFailure): void
    {
        try {
            $detail = $callback();
            $checks[$name] = ['ok' => true, 'critical' => $critical, 'detail' => $detail];
        } catch (Throwable $e) {
            $checks[$name] = ['ok' => false, 'critical' => $critical, 'detail' => $e->getMessage()];
            if ($critical) $criticalFailure = true;
        }
    }
}
