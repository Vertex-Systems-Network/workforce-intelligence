<?php

namespace Database\Seeders;

use App\Models\TaskTag;
use App\Models\Workspace;
use App\Services\Tasks\TaskWorkflowService;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** Provides p5 task engine seeder behavior within the WorkIntel application. */ class TaskEngineSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        PermissionCatalog::sync();
        if (! Schema::hasTable('task_statuses')) return;

        $workflow = app(TaskWorkflowService::class);
        Workspace::query()->orderBy('id')->chunkById(100, function ($workspaces) use ($workflow) {
            foreach ($workspaces as $workspace) $workflow->ensureDefaults($workspace);
        });

        // Demo-only labels help exercise the multi-tag UI without injecting
        // workflow behavior into real workspaces.
        $demo = Workspace::query()->where('slug', 'acme-corp')->first();
        if ($demo && Schema::hasTable('task_tags')) {
            foreach ([
                ['name' => 'Client', 'slug' => 'client', 'color' => '#7c3aed'],
                ['name' => 'Urgent', 'slug' => 'urgent', 'color' => '#dc2626'],
                ['name' => 'Backend', 'slug' => 'backend', 'color' => '#2563eb'],
            ] as $tag) TaskTag::firstOrCreate(['workspace_id' => $demo->id, 'slug' => $tag['slug']], $tag);
        }
    }
}
