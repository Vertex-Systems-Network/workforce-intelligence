<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create per-user, per-workspace page customization storage without modifying workspace defaults. */
    public function up(): void
    {
        if (! Schema::hasTable('user_page_preferences')) {
            Schema::create('user_page_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->string('page_key', 80);
                $table->json('settings');
                $table->timestamps();
                $table->unique(['user_id', 'workspace_id', 'page_key'], 'user_page_pref_scope_unique');
                $table->index(['workspace_id', 'page_key'], 'user_page_pref_workspace_page_idx');
            });
        }
    }

    /** Remove the page customization table when this migration is rolled back. */
    public function down(): void
    {
        Schema::dropIfExists('user_page_preferences');
    }
};
