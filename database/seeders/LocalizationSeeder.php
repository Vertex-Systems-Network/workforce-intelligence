<?php

namespace Database\Seeders;

use App\Support\LocaleCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Provides p7 localization seeder behavior within the WorkIntel application. */ class LocalizationSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        if (Schema::hasTable('users')) {
            DB::table('users')->where(function($q){$q->whereNull('locale')->orWhere('locale','');})->update(['locale'=>'en']);
            if (Schema::hasColumn('users','use_workspace_locale')) {
                DB::table('users')->whereNull('use_workspace_locale')->update(['use_workspace_locale'=>true]);
            }
        }
        if (Schema::hasTable('workspace_preferences')) {
            DB::table('workspace_preferences')->where(function($q){$q->whereNull('default_language')->orWhere('default_language','');})->update(['default_language'=>'en']);
            // Preserve every already-supported locale; only repair invalid legacy values.
            DB::table('workspace_preferences')->whereNotIn('default_language',LocaleCatalog::SUPPORTED)->update(['default_language'=>'en']);
        }
    }
}
