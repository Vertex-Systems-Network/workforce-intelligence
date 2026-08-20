<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Verifies the runtime-directory and UI-customization landmarks required by the stabilized release. */
class UiRuntimeDoctor extends Command
{
    protected $signature = 'workintel:ui-runtime-doctor';
    protected $description = 'Check WorkIntel runtime directories, page preferences and UI stabilization assets';

    /** Run the UI/runtime stabilization diagnostics and return a non-zero status on blocking failures. */
    public function handle(): int
    {
        $checks = [
            ['runtime views', is_dir(storage_path('framework/views')) && is_writable(storage_path('framework/views'))],
            ['runtime sessions', is_dir(storage_path('framework/sessions')) && is_writable(storage_path('framework/sessions'))],
            ['runtime cache', is_dir(storage_path('framework/cache/data')) && is_writable(storage_path('framework/cache/data'))],
            ['runtime logs', is_dir(storage_path('logs')) && is_writable(storage_path('logs'))],
            ['bootstrap cache', is_dir(base_path('bootstrap/cache')) && is_writable(base_path('bootstrap/cache'))],
            ['page preferences table', Schema::hasTable('user_page_preferences')],
            ['page customization frontend', is_file(resource_path('js/design-system/PageCustomization.tsx'))],
            ['toast frontend', is_file(resource_path('js/design-system/toast.tsx'))],
            ['dashboard builder', is_file(resource_path('js/components/DashboardGrid.tsx'))],
            ['runtime preparer', is_file(base_path('tools/prepare-runtime.php'))],
            ['package manifest builder', is_file(base_path('tools/discover-packages.php'))],
        ];

        $errors = 0;
        foreach ($checks as [$name, $ok]) {
            $this->line(sprintf('[%s] %s', $ok ? 'OK' : 'FAIL', $name));
            if (! $ok) $errors++;
        }

        if ($errors) {
            $this->error("UI/runtime doctor found {$errors} blocking issue(s).");
            return self::FAILURE;
        }

        $this->info('UI/runtime doctor passed.');
        return self::SUCCESS;
    }
}
