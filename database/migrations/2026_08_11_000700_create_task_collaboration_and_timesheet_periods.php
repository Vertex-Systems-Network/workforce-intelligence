<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('task_comments')) {
            Schema::create('task_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->text('body');
                $table->timestamps();
                $table->index(['task_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('task_attachments')) {
            Schema::create('task_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('disk', 40)->default('local');
                $table->string('path', 500);
                $table->string('original_name', 255);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('timesheet_periods')) {
            Schema::create('timesheet_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->date('week_start');
                $table->date('week_end');
                $table->string('status', 24)->default('open');
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'member_id', 'week_start']);
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('timesheet_periods');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_comments');
    }
};
