<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Provides database seeder behavior within the WorkIntel application. */ class DatabaseSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'DatabaseSeeder contains demo identities and must never run in production. Run only the explicitly reviewed production-safe seeders required by the deployment.'
            );
        }

        $this->call([
            PermissionSeeder::class,
            SubscriptionPlanSeeder::class,
            DemoWorkspaceSeeder::class,
            HrisSeeder::class,
            PerformanceSeeder::class,
            FinanceSeeder::class,
            PayrollComplianceSeeder::class,
            FieldWorkforceSeeder::class,
            EnterpriseSeeder::class,
            AutomationSeeder::class,
            IntelligenceSeeder::class,
            PlatformSeeder::class,
            IdentitySeeder::class,
            AccessControlSeeder::class,
            SettingsSeeder::class,
            ModuleManagerSeeder::class,
            TaskEngineSeeder::class,
            DocumentTemplateSeeder::class,
            LocalizationSeeder::class,
            ScreenshotStorageSeeder::class,
            ChatCollaborationSeeder::class,
            CommerceSeeder::class,
        ]);
    }
}
