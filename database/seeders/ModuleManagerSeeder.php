<?php

namespace Database\Seeders;

use App\Models\Workspace;
use App\Services\Modules\WorkspaceModuleService;
use Illuminate\Database\Seeder;

/** Provides p4 module seeder behavior within the WorkIntel application. */ class ModuleManagerSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        $service = app(WorkspaceModuleService::class);
        Workspace::query()->orderBy('id')->chunkById(100, function ($workspaces) use ($service) {
            foreach ($workspaces as $workspace) $service->initializeWorkspace($workspace);
        });
    }
}
