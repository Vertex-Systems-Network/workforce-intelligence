<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            foreach ([
                ['Documents', 'documents.view'],
                ['Documents', 'documents.generate'],
                ['Documents', 'documents.manage'],
                ['Documents', 'documents.templates_manage'],
            ] as [$group, $slug]) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
                );
            }
        }

        if (! Schema::hasTable('document_templates')) {
            Schema::create('document_templates', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
                $table->string('name', 160);
                $table->string('slug', 120);
                $table->string('document_type', 60);
                $table->string('language', 12)->default('en');
                $table->string('status', 20)->default('active');
                $table->boolean('is_default')->default(false);
                $table->string('paper_size', 20)->default('A4');
                $table->string('orientation', 20)->default('portrait');
                $table->char('primary_color', 7)->default('#111827');
                $table->char('secondary_color', 7)->default('#6B7280');
                $table->string('font_family', 60)->default('Helvetica');
                $table->json('content_schema');
                $table->json('settings')->nullable();
                $table->unsignedInteger('current_version')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['workspace_id', 'slug'], 'dt_ws_slug_uq');
                $table->index(['workspace_id', 'document_type', 'status'], 'dt_ws_type_status_idx');
                $table->index(['workspace_id', 'document_type', 'language', 'is_default'], 'dt_ws_type_lang_default_idx');
            });
        }

        if (! Schema::hasTable('document_template_versions')) {
            Schema::create('document_template_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->json('content_schema');
                $table->json('settings')->nullable();
                $table->string('change_note', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['document_template_id', 'version'], 'dtv_template_version_uq');
            });
        }

        if (! Schema::hasTable('generated_documents')) {
            Schema::create('generated_documents', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
                $table->string('document_type', 60);
                $table->string('source_type', 80)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('language', 12)->default('en');
                $table->string('status', 20)->default('completed');
                $table->string('disk', 40)->default('local');
                $table->string('path', 700);
                $table->string('filename', 255);
                $table->string('mime_type', 100)->default('application/pdf');
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->char('sha256', 64);
                $table->json('variables_snapshot')->nullable();
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('generated_at')->useCurrent();
                $table->timestamps();
                $table->index(['workspace_id', 'document_type', 'generated_at'], 'gd_ws_type_generated_idx');
                $table->index(['workspace_id', 'source_type', 'source_id'], 'gd_ws_source_idx');
            });
        }

        if (Schema::hasTable('roles') && Schema::hasTable('role_permissions') && Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')->whereIn('slug', ['documents.view','documents.generate','documents.manage','documents.templates_manage'])->pluck('id','slug');
            $hasTimestamps = Schema::hasColumn('role_permissions','created_at') && Schema::hasColumn('role_permissions','updated_at');
            foreach (DB::table('roles')->where('is_system', true)->get(['id','slug']) as $role) {
                $slugs = match ($role->slug) {
                    'owner','admin' => ['documents.view','documents.generate','documents.manage','documents.templates_manage'],
                    'hr','payroll-manager' => ['documents.view','documents.generate'],
                    'manager','team-lead' => ['documents.view'],
                    default => [],
                };
                foreach ($slugs as $slug) {
                    if (! isset($permissionIds[$slug])) continue;
                    $row=['role_id'=>$role->id,'permission_id'=>$permissionIds[$slug]];
                    if($hasTimestamps)$row += ['created_at'=>now(),'updated_at'=>now()];
                    DB::table('role_permissions')->insertOrIgnore($row);
                }
            }
        }

        if (Schema::hasTable('workspace_modules')) {
            foreach (DB::table('workspaces')->pluck('id') as $workspaceId) {
                DB::table('workspace_modules')->insertOrIgnore([
                    'workspace_id' => $workspaceId,
                    'module_key' => 'documents',
                    'is_enabled' => true,
                    'navigation_visible' => true,
                    'background_processing' => true,
                    'enabled_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('document_template_versions');
        Schema::dropIfExists('document_templates');
    }
};
