<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('job_titles')) {
            Schema::create('job_titles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('code', 32)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
                $table->unique(['workspace_id', 'name']);
            });
        }

        if (! Schema::hasColumn('workspace_members', 'job_title_id')) {
            Schema::table('workspace_members', fn (Blueprint $table) => $table->foreignId('job_title_id')->nullable()->constrained('job_titles')->nullOnDelete());
        }

        // Preserve existing job-title text when upgrading an already-used workspace.
        // New records use job_title_id, while the legacy text column remains readable
        // during the migration period.
        $legacyTitles = DB::table('workspace_members')
            ->select(['workspace_id', 'job_title'])
            ->whereNotNull('job_title')
            ->where('job_title', '<>', '')
            ->distinct()
            ->get();

        foreach ($legacyTitles as $legacyTitle) {
            $jobTitleId = DB::table('job_titles')
                ->where('workspace_id', $legacyTitle->workspace_id)
                ->where('name', $legacyTitle->job_title)
                ->value('id');

            if (! $jobTitleId) {
                $jobTitleId = DB::table('job_titles')->insertGetId([
                    'workspace_id' => $legacyTitle->workspace_id,
                    'name' => $legacyTitle->job_title,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('workspace_members')
                ->where('workspace_id', $legacyTitle->workspace_id)
                ->where('job_title', $legacyTitle->job_title)
                ->update(['job_title_id' => $jobTitleId]);
        }

        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('name', 120);
                $table->string('code', 32)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
                $table->unique(['workspace_id', 'name']);
            });
        }

        if (! Schema::hasTable('team_members')) {
            Schema::create('team_members', function (Blueprint $table) {
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('role', 60)->nullable();
                $table->timestamps();
                $table->primary(['team_id', 'member_id']);
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');

        if (Schema::hasColumn('workspace_members', 'job_title_id')) {
            Schema::table('workspace_members', fn (Blueprint $table) => $table->dropConstrainedForeignId('job_title_id'));
        }

        Schema::dropIfExists('job_titles');
    }
};
