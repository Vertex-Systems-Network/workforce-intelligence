<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Adds staging previews, review comments and linked reusable-component ownership to Website Studio. */
    public function up(): void
    {
        if (Schema::hasTable('website_pages') && ! Schema::hasColumn('website_pages', 'staged_version')) {
            Schema::table('website_pages', function (Blueprint $table) {
                $table->unsignedInteger('staged_version')->nullable()->after('published_version');
                $table->timestamp('staged_at')->nullable()->after('published_at');
                $table->index(['website_site_id', 'staged_version'], 'wpage_site_staged_idx');
            });
        }

        if (! Schema::hasTable('website_preview_tokens')) {
            Schema::create('website_preview_tokens', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('website_site_id')->constrained('website_sites')->cascadeOnDelete();
                $table->foreignId('website_page_id')->constrained('website_pages')->cascadeOnDelete();
                $table->char('token_hash', 64)->unique();
                $table->string('source', 24)->default('staging');
                $table->unsignedInteger('version')->nullable();
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamp('expires_at');
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('last_viewed_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['website_page_id', 'expires_at'], 'wpreview_page_exp_idx');
            });
        }

        if (! Schema::hasTable('website_page_comments')) {
            Schema::create('website_page_comments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('website_page_id')->constrained('website_pages')->cascadeOnDelete();
                $table->string('section_id', 120)->nullable();
                $table->text('message');
                $table->string('status', 20)->default('open');
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->foreignId('resolved_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['website_page_id', 'status', 'created_at'], 'wcomment_page_status_idx');
            });
        }

        if (! Schema::hasTable('website_reusable_section_links')) {
            Schema::create('website_reusable_section_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('website_page_id')->constrained('website_pages')->cascadeOnDelete();
                $table->foreignId('website_reusable_section_id');
                $table->foreign('website_reusable_section_id', 'wlink_reusable_fk')->references('id')->on('website_reusable_sections')->cascadeOnDelete();
                $table->string('instance_id', 120);
                $table->timestamps();
                $table->unique(['website_page_id', 'instance_id'], 'wlink_page_instance_uq');
                $table->index(['website_reusable_section_id', 'website_page_id'], 'wlink_reusable_page_idx');
            });
        }
    }

    /** Removes only the Website Studio V3 staging/review/link schema introduced by this migration. */
    public function down(): void
    {
        Schema::dropIfExists('website_reusable_section_links');
        Schema::dropIfExists('website_page_comments');
        Schema::dropIfExists('website_preview_tokens');
        if (Schema::hasTable('website_pages') && Schema::hasColumn('website_pages', 'staged_version')) {
            Schema::table('website_pages', function (Blueprint $table) {
                $table->dropIndex('wpage_site_staged_idx');
                $table->dropColumn(['staged_version', 'staged_at']);
            });
        }
    }
};
