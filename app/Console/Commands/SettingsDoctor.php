<?php

namespace App\Console\Commands;

use App\Models\Permission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Provides p3 settings doctor behavior within the WorkIntel application. */ class SettingsDoctor extends Command
{
    protected $signature = 'workintel:p3-doctor';
    protected $description = 'Validate the P3 Global Settings Center schema and permission contract.';

    /** Executes the command, job, or request handler. */ public function handle(): int
    {
        $errors = [];
        if (! Schema::hasTable('workspace_preferences')) $errors[] = 'Missing workspace_preferences table.';
        foreach (['timezone','currency','country','week_starts_on'] as $column) if (! Schema::hasColumn('workspaces', $column)) $errors[] = "Missing workspaces.{$column}.";
        foreach (['settings.view','settings.manage'] as $slug) if (! Permission::where('slug', $slug)->exists()) $errors[] = "Missing permission {$slug}.";
        if ($errors) { foreach ($errors as $error) $this->error($error); return self::FAILURE; }
        $this->info('P3 Global Settings Center: PASS');
        return self::SUCCESS;
    }
}
