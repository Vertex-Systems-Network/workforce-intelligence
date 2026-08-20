<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Creates the workspace website builder, versioning, reusable-section and lead-capture schema. */
    public function up(): void
    {
        if (Schema::hasTable('workspace_domains') && ! Schema::hasColumn('workspace_domains', 'purpose')) {
            Schema::table('workspace_domains', function (Blueprint $table) {
                $table->string('purpose', 24)->default('workspace')->after('workspace_id')->index('wdom_purpose_idx');
            });
        }

        if (! Schema::hasTable('website_sites')) {
            Schema::create('website_sites', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('name', 160);
                $table->string('status', 24)->default('draft');
                $table->string('default_language', 12)->default('en');
                $table->json('supported_languages')->nullable();
                $table->json('theme')->nullable();
                $table->json('header_config')->nullable();
                $table->json('footer_config')->nullable();
                $table->json('seo_defaults')->nullable();
                $table->foreignId('custom_domain_id')->nullable()->constrained('workspace_domains')->nullOnDelete();
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->foreignId('updated_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'status'], 'wsite_ws_status_idx');
            });
        }

        if (! Schema::hasTable('website_pages')) {
            Schema::create('website_pages', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('website_site_id')->constrained('website_sites')->cascadeOnDelete();
                $table->string('page_type', 32)->default('custom');
                $table->string('language', 12)->default('en');
                $table->string('title', 180);
                $table->string('slug', 180)->default('home');
                $table->string('status', 24)->default('draft');
                $table->boolean('is_home')->default(false);
                $table->boolean('navigation_visible')->default(true);
                $table->string('navigation_label', 120)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(1000);
                $table->unsignedInteger('current_version')->default(1);
                $table->unsignedInteger('published_version')->nullable();
                $table->string('seo_title', 180)->nullable();
                $table->text('seo_description')->nullable();
                $table->foreignId('og_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->foreignId('updated_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->unique(['website_site_id', 'language', 'slug'], 'wpage_site_lang_slug_uq');
                $table->index(['website_site_id', 'language', 'status'], 'wpage_site_lang_status_idx');
                $table->index(['workspace_id', 'is_home'], 'wpage_ws_home_idx');
            });
        }

        if (! Schema::hasTable('website_page_versions')) {
            Schema::create('website_page_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_page_id')->constrained('website_pages')->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->json('schema');
                $table->string('change_note', 500)->nullable();
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['website_page_id', 'version'], 'wpver_page_version_uq');
                $table->index(['website_page_id', 'published_at'], 'wpver_page_published_idx');
            });
        }

        if (! Schema::hasTable('website_reusable_sections')) {
            Schema::create('website_reusable_sections', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 160);
                $table->string('section_type', 40)->default('custom');
                $table->json('schema');
                $table->boolean('is_global')->default(false);
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->foreignId('updated_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'section_type'], 'wreuse_ws_type_idx');
            });
        }

        if (! Schema::hasTable('website_forms')) {
            Schema::create('website_forms', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('website_site_id')->constrained('website_sites')->cascadeOnDelete();
                $table->foreignId('website_page_id')->nullable()->constrained('website_pages')->nullOnDelete();
                $table->string('name', 160);
                $table->string('slug', 120);
                $table->string('status', 24)->default('active');
                $table->json('fields');
                $table->json('settings')->nullable();
                $table->text('success_message')->nullable();
                $table->json('notification_emails')->nullable();
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamps();
                $table->unique(['website_site_id', 'slug'], 'wform_site_slug_uq');
                $table->index(['workspace_id', 'status'], 'wform_ws_status_idx');
            });
        }

        if (! Schema::hasTable('website_form_submissions')) {
            Schema::create('website_form_submissions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('website_form_id')->constrained('website_forms')->cascadeOnDelete();
                $table->foreignId('website_page_id')->nullable()->constrained('website_pages')->nullOnDelete();
                $table->longText('payload');
                $table->string('status', 24)->default('new');
                $table->boolean('consent')->default(false);
                $table->string('source_url', 1000)->nullable();
                $table->char('ip_hash', 64)->nullable();
                $table->char('user_agent_hash', 64)->nullable();
                $table->text('internal_note')->nullable();
                $table->timestamp('submitted_at')->useCurrent();
                $table->timestamps();
                $table->index(['workspace_id', 'status', 'submitted_at'], 'wsub_ws_status_date_idx');
                $table->index(['website_form_id', 'submitted_at'], 'wsub_form_date_idx');
            });
        }
    }

    /** Removes only the Website & Portal Builder schema introduced by Block H. */
    public function down(): void
    {
        Schema::dropIfExists('website_form_submissions');
        Schema::dropIfExists('website_forms');
        Schema::dropIfExists('website_reusable_sections');
        Schema::dropIfExists('website_page_versions');
        Schema::dropIfExists('website_pages');
        Schema::dropIfExists('website_sites');
        if (Schema::hasTable('workspace_domains') && Schema::hasColumn('workspace_domains', 'purpose')) {
            Schema::table('workspace_domains', fn (Blueprint $table) => $table->dropColumn('purpose'));
        }
    }
};
