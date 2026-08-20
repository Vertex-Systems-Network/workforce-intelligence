<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('time_sessions')) {
            Schema::create('time_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamp('started_at');
                $table->timestamp('stopped_at')->nullable();
                $table->string('status', 20)->default('running');
                $table->string('source', 20)->default('web');
                $table->boolean('billable')->default(true);
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'member_id', 'status']);
            });
        }

        if (! Schema::hasTable('time_session_events')) {
            Schema::create('time_session_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('time_session_id')->constrained()->cascadeOnDelete();
                $table->string('event_type', 50);
                $table->timestamp('occurred_at');
                $table->json('metadata')->nullable();
                $table->index(['time_session_id', 'occurred_at']);
            });
        }

        if (! Schema::hasTable('time_entries')) {
            Schema::create('time_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('time_session_id')->nullable()->constrained()->nullOnDelete();
                $table->date('date');
                $table->timestamp('started_at');
                $table->timestamp('ended_at');
                $table->unsignedInteger('duration_seconds');
                $table->boolean('billable')->default(true);
                $table->string('source', 20)->default('web');
                $table->string('approval_status', 24)->default('draft');
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'member_id', 'date']);
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('time_session_events');
        Schema::dropIfExists('time_sessions');
    }
};
