<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Removes obsolete development-stage migration IDs after their semantic replacement migrations have run. */
    public function up(): void
    {
        if (! Schema::hasTable('migrations')) {
            return;
        }

        DB::table('migrations')->whereIn('migration', [
            '2026_08_11_001850_repair_m13_operational_tables',
            '2026_08_11_002810_repair_phase18_23_role_permissions',
            '2026_08_11_002820_repair_phase23_retention_and_scim',
            '2026_08_12_000100_p0_stability_role_repairs',
        ])->delete();
    }

    /** Keeps rollback non-destructive because deleted legacy IDs refer to files no longer shipped. */
    public function down(): void
    {
        // Intentionally no-op: restoring obsolete migration IDs would reintroduce stage-based history labels.
    }
};
