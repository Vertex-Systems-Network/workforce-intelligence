<?php

namespace Database\Seeders;

use App\Models\Workspace;
use App\Models\WorkspacePreference;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Provides p3 settings seeder behavior within the WorkIntel application. */ class SettingsSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        PermissionCatalog::sync();
        if (! Schema::hasTable('workspace_preferences')) return;

        Workspace::query()->orderBy('id')->each(function (Workspace $workspace) {
            WorkspacePreference::firstOrCreate(
                ['workspace_id' => $workspace->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'app_title' => $workspace->name,
                    'company_name' => $workspace->name,
                    'default_language' => 'en',
                    'date_format' => 'YYYY-MM-DD',
                    'time_format' => '24h',
                    'fiscal_year_start_month' => 1,
                    'number_format' => '1,234.56',
                    'decimal_separator' => '.',
                    'thousands_separator' => ',',
                    'default_theme' => 'system',
                    'sidebar_density' => 'comfortable',
                    'accent_color' => '#6366F1',
                ]
            );
        });
    }
}
