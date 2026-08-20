<?php

namespace Database\Seeders;

use App\Support\PlanCatalog;
use Illuminate\Database\Seeder;

/** Provides subscription plan seeder behavior within the WorkIntel application. */ class SubscriptionPlanSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        PlanCatalog::sync();
    }
}
