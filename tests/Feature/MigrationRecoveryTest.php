<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Provides migration recovery test behavior within the WorkIntel application. */ class MigrationRecoveryTest extends TestCase
{
    /** Handles the test payroll migration can retry when projects completed at already exists operation for the current WorkIntel workflow. */ public function test_payroll_migration_can_retry_when_projects_completed_at_already_exists(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->assertTrue(Schema::hasColumn('projects', 'completed_at'));

        Schema::disableForeignKeyConstraints();
        foreach (['payroll_actions','payroll_item_projects','payroll_adjustments','payroll_items','payroll_runs','compensation_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        DB::table('migrations')->where('migration', '2026_08_11_001400_create_compensation_and_payroll_tables')->delete();

        $exit = Artisan::call('migrate', ['--force' => true]);
        $this->assertSame(0, $exit, Artisan::output());
        $this->assertTrue(Schema::hasTable('compensation_profiles'));
        $this->assertTrue(Schema::hasTable('payroll_runs'));
        $this->assertTrue(Schema::hasColumn('projects', 'completed_at'));
    }
    /** Handles the test security migration can retry with legacy permission schema and partial tables operation for the current WorkIntel workflow. */ public function test_security_migration_can_retry_with_legacy_permission_schema_and_partial_tables(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertFalse(Schema::hasColumn('permissions', 'created_at'));
        $this->assertFalse(Schema::hasColumn('permissions', 'updated_at'));
        $this->assertTrue(Schema::hasTable('notification_preferences'));
        $this->assertTrue(Schema::hasTable('audit_logs'));
        $this->assertTrue(Schema::hasColumn('agent_enrollments', 'browser_used_at'));

        DB::table('migrations')->where('migration', '2026_08_11_001800_create_notifications_integrations_security_tables')->delete();

        $exit = Artisan::call('migrate', ['--force' => true]);
        $this->assertSame(0, $exit, Artisan::output());

        $this->assertTrue(DB::table('permissions')->where('slug', 'notifications.manage')->exists());
        $this->assertTrue(DB::table('permissions')->where('slug', 'security.manage')->exists());
        $this->assertTrue(Schema::hasTable('security_events'));
        $this->assertTrue(Schema::hasTable('webhook_endpoints'));
    }

}
