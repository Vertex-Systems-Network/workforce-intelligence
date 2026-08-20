<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('shifts')) {
            Schema::create('shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->time('start_time');
                $table->time('end_time');
                $table->unsignedSmallInteger('break_minutes')->default(60);
                $table->unsignedSmallInteger('grace_minutes')->default(10);
                $table->string('location_type', 24)->default('office');
                $table->string('timezone', 64)->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shift_assignments')) {
            Schema::create('shift_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->date('date');
                $table->string('work_mode', 24)->nullable();
                $table->timestamps();
                $table->unique(['member_id', 'date']);
                $table->index(['workspace_id', 'date']);
            });
        }

        if (! Schema::hasTable('attendance_records')) {
            Schema::create('attendance_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('shift_assignment_id')->nullable()->constrained()->nullOnDelete();
                $table->date('date');
                $table->timestamp('clock_in_at')->nullable();
                $table->timestamp('clock_out_at')->nullable();
                $table->unsignedInteger('break_seconds')->default(0);
                $table->unsignedInteger('worked_seconds')->default(0);
                $table->unsignedSmallInteger('late_minutes')->default(0);
                $table->unsignedSmallInteger('overtime_minutes')->default(0);
                $table->string('status', 24)->default('present');
                $table->string('source', 24)->default('web');
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'member_id', 'date']);
                $table->index(['workspace_id', 'date', 'status']);
            });
        }

        if (! Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('code', 32);
                $table->boolean('is_paid')->default(true);
                $table->decimal('annual_allowance_days', 6, 2)->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();
                $table->unique(['workspace_id', 'code']);
            });
        }

        if (! Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('days', 6, 2);
                $table->text('reason')->nullable();
                $table->string('status', 24)->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'status', 'start_date']);
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shifts');
    }
};
