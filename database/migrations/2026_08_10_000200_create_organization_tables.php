<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('code', 32)->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'name']);
            });
        }

        if (! Schema::hasTable('workspace_members')) {
            Schema::create('workspace_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('employee_code', 40)->nullable();
                $table->string('job_title', 120)->nullable();
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('manager_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('employment_type', 30)->default('full_time');
                $table->date('joining_date')->nullable();
                $table->string('status', 24)->default('active');
                $table->string('timezone', 64)->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'user_id']);
                $table->unique(['workspace_id', 'employee_code']);
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('departments');
    }
};
