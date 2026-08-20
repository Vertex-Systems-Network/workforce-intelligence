<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Persists per-member collaboration-inbox triage without changing message/read history. */
    public function up(): void
    {
        if (! Schema::hasTable('chat_activity_states')) {
            Schema::create('chat_activity_states', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('activity_type', 24);
                $table->string('activity_key', 80);
                $table->string('status', 20)->default('open');
                $table->timestamp('snoozed_until')->nullable();
                $table->timestamp('follow_up_at')->nullable();
                $table->timestamps();
                $table->unique(['member_id','activity_type','activity_key'], 'chat_activity_member_type_key_uq');
                $table->index(['workspace_id','member_id','status'], 'chat_activity_ws_member_status_idx');
                $table->index(['member_id','snoozed_until'], 'chat_activity_member_snooze_idx');
            });
        }
    }

    /** Removes only the M10 collaboration-inbox triage state. */
    public function down(): void
    {
        Schema::dropIfExists('chat_activity_states');
    }
};
