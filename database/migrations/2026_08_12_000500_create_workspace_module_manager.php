<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('workspace_modules')) {
            Schema::create('workspace_modules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('module_key', 64);
                $table->boolean('is_enabled')->default(true);
                $table->boolean('navigation_visible')->default(true);
                $table->boolean('background_processing')->default(true);
                $table->string('label_override', 80)->nullable();
                $table->json('settings')->nullable();
                $table->timestamp('enabled_at')->nullable();
                $table->foreignId('enabled_by')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamp('disabled_at')->nullable();
                $table->foreignId('disabled_by')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamps();
                $table->unique(['workspace_id', 'module_key'], 'wm_workspace_module_uq');
                $table->index(['workspace_id', 'is_enabled'], 'wm_workspace_enabled_idx');
            });
        }

        if (! Schema::hasTable('workspace_module_events')) {
            Schema::create('workspace_module_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('module_key', 64);
                $table->foreignId('actor_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('action', 40);
                $table->json('before_state')->nullable();
                $table->json('after_state')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'module_key', 'created_at'], 'wme_ws_module_created_idx');
            });
        }

        if (Schema::hasTable('permissions')) {
            foreach ([
                ['Modules', 'modules.view'],
                ['Modules', 'modules.manage'],
            ] as [$group, $slug]) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
                );
            }
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('workspace_module_events');
        Schema::dropIfExists('workspace_modules');
    }
};
