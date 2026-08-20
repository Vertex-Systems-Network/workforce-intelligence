<?php

namespace Database\Seeders;

use App\Models\Workspace;
use App\Services\Intelligence\IntelligenceRuleCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** Provides phase25 intelligence seeder behavior within the WorkIntel application. */ class IntelligenceSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        if (! Schema::hasTable('intelligence_settings') || ! Schema::hasTable('intelligence_rules')) return;
        $workspace = Workspace::query()->where('slug', 'acme-corp')->first();
        if (! $workspace) return;

        // Seed only deterministic configuration. Actual insights are always
        // calculated from operational source data by the intelligence engine.
        app(IntelligenceRuleCatalog::class)->ensureWorkspace($workspace);
    }
}
