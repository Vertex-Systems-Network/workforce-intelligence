<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users','use_workspace_locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('use_workspace_locale')->default(true)->after('locale');
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users','use_workspace_locale')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('use_workspace_locale'));
        }
    }
};
