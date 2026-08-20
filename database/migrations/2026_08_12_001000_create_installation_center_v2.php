<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('installation_guide_progress')) {
            Schema::create('installation_guide_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('guide_key', 80);
                $table->json('completed_steps')->nullable();
                $table->string('current_step', 80)->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id','member_id','guide_key'], 'igp_ws_member_guide_uq');
                $table->index(['workspace_id','completed_at'], 'igp_ws_completed_idx');
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('installation_guide_progress');
    }
};
