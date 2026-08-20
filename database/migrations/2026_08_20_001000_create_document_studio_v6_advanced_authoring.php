<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds advanced V6 authoring resources without changing existing generated-document history. */
    public function up(): void
    {
        if (! Schema::hasTable('document_brand_kits')) {
            Schema::create('document_brand_kits', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->char('primary_color', 7)->default('#111827');
                $table->char('secondary_color', 7)->default('#6B7280');
                $table->char('accent_color', 7)->default('#2563EB');
                $table->string('font_family', 60)->default('Arial');
                $table->string('heading_font_family', 60)->default('Arial');
                $table->foreignId('logo_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
                $table->json('settings')->nullable();
                $table->boolean('is_default')->default(false);
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->foreignId('updated_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamps();
                $table->unique(['workspace_id', 'name'], 'doc_brand_workspace_name_uq');
                $table->index(['workspace_id', 'is_default'], 'doc_brand_workspace_default_idx');
            });
        }

        if (! Schema::hasTable('document_page_masters')) {
            Schema::create('document_page_masters', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->json('page_settings');
                $table->json('header_settings')->nullable();
                $table->json('footer_settings')->nullable();
                $table->json('watermark_settings')->nullable();
                $table->boolean('is_default')->default(false);
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->foreignId('updated_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamps();
                $table->unique(['workspace_id', 'name'], 'doc_master_workspace_name_uq');
                $table->index(['workspace_id', 'is_default'], 'doc_master_workspace_default_idx');
            });
        }

        if (! Schema::hasTable('document_batch_jobs')) {
            Schema::create('document_batch_jobs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
                $table->string('source_type', 80)->nullable();
                $table->json('source_ids');
                $table->string('status', 20)->default('queued');
                $table->unsignedInteger('requested_count')->default(0);
                $table->unsignedInteger('processed_count')->default(0);
                $table->unsignedInteger('generated_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->json('results')->nullable();
                $table->foreignId('requested_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'status', 'created_at'], 'doc_batch_workspace_status_idx');
                $table->index(['document_template_id', 'created_at'], 'doc_batch_template_created_idx');
            });
        }

        if (Schema::hasTable('document_components') && ! Schema::hasColumn('document_components', 'version')) {
            Schema::table('document_components', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('settings');
            });
        }
    }

    /** Removes only advanced V6 resources created by this migration. */
    public function down(): void
    {
        if (Schema::hasTable('document_components') && Schema::hasColumn('document_components', 'version')) {
            Schema::table('document_components', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
        Schema::dropIfExists('document_batch_jobs');
        Schema::dropIfExists('document_page_masters');
        Schema::dropIfExists('document_brand_kits');
    }
};
