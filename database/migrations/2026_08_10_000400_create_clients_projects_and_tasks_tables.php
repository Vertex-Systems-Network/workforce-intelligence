<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('clients')) {
            Schema::create('clients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('company_name', 150)->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 40)->nullable();
                $table->char('currency', 3)->default('USD');
                $table->decimal('billing_rate', 12, 2)->nullable();
                $table->string('status', 24)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 160);
                $table->string('code', 40)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 24)->default('active');
                $table->string('priority', 20)->default('medium');
                $table->date('start_date')->nullable();
                $table->date('due_date')->nullable();
                $table->string('budget_type', 24)->default('hours');
                $table->decimal('budget_amount', 14, 2)->nullable();
                $table->unsignedInteger('estimated_minutes')->nullable();
                $table->boolean('billable')->default(true);
                $table->char('currency', 3)->default('USD');
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->unique(['workspace_id', 'code']);
            });
        }

        if (! Schema::hasTable('project_members')) {
            Schema::create('project_members', function (Blueprint $table) {
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('role', 80)->nullable();
                $table->decimal('hourly_cost', 12, 2)->nullable();
                $table->decimal('billing_rate', 12, 2)->nullable();
                $table->timestamps();
                $table->primary(['project_id', 'member_id']);
            });
        }

        if (! Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('tasks')->nullOnDelete();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->string('status', 24)->default('todo');
                $table->string('priority', 20)->default('medium');
                $table->unsignedInteger('estimated_minutes')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->boolean('billable')->default(true);
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('task_assignees')) {
            Schema::create('task_assignees', function (Blueprint $table) {
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['task_id', 'member_id']);
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('clients');
    }
};
