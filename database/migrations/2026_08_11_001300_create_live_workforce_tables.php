<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('worker_presences')) {
            Schema::create('worker_presences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
                $table->string('status', 24)->default('offline');
                $table->string('tracking_status', 24)->nullable();
                $table->string('app_name', 180)->nullable();
                $table->string('domain', 253)->nullable();
                $table->unsignedTinyInteger('activity_percent')->nullable();
                $table->timestamp('timer_started_at')->nullable();
                $table->timestamp('status_since')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
    
                $table->unique(['workspace_id', 'member_id']);
                $table->index(['workspace_id', 'status']);
                $table->index(['workspace_id', 'last_seen_at']);
            });
        }

        if (! Schema::hasTable('work_events')) {
            Schema::create('work_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
                $table->string('event_type', 80);
                $table->string('source', 40);
                $table->string('title', 220)->nullable();
                $table->text('detail')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->unsignedTinyInteger('activity_percent')->nullable();
                $table->string('dedupe_key', 180)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
    
                $table->unique(['workspace_id', 'dedupe_key']);
                $table->index(['workspace_id', 'member_id', 'started_at']);
                $table->index(['workspace_id', 'event_type', 'started_at']);
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('work_events');
        Schema::dropIfExists('worker_presences');
    }
};
