<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

/** Provides permission seeder behavior within the WorkIntel application. */ class PermissionSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        PermissionCatalog::sync();
    }
}
