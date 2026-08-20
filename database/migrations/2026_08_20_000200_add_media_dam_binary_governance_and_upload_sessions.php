<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Completes Media DAM V3 with binary versioning, renditions, rights governance, collection sharing and resumable uploads. */
return new class extends Migration
{
    /** Apply additive DAM closure schema without changing existing asset identifiers or public URLs. */
    public function up(): void
    {
        if (Schema::hasTable('media_assets')) {
            Schema::table('media_assets', function (Blueprint $table): void {
                if (! Schema::hasColumn('media_assets', 'copyright_owner')) $table->string('copyright_owner', 180)->nullable()->after('caption');
                if (! Schema::hasColumn('media_assets', 'license_type')) $table->string('license_type', 80)->nullable()->after('copyright_owner');
                if (! Schema::hasColumn('media_assets', 'license_reference')) $table->string('license_reference', 255)->nullable()->after('license_type');
                if (! Schema::hasColumn('media_assets', 'license_expires_at')) $table->date('license_expires_at')->nullable()->after('license_reference');
                if (! Schema::hasColumn('media_assets', 'usage_restrictions')) $table->text('usage_restrictions')->nullable()->after('license_expires_at');
                if (! Schema::hasColumn('media_assets', 'rights_review_at')) $table->date('rights_review_at')->nullable()->after('usage_restrictions');
            });
        }

        if (Schema::hasTable('media_collections')) {
            Schema::table('media_collections', function (Blueprint $table): void {
                if (! Schema::hasColumn('media_collections', 'visibility')) $table->string('visibility', 20)->default('workspace')->after('description');
            });
        }

        if (! Schema::hasTable('media_collection_members')) {
            Schema::create('media_collection_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('media_collection_id')->constrained('media_collections')->cascadeOnDelete();
                $table->foreignId('workspace_member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('role', 20)->default('viewer');
                $table->timestamps();
                $table->unique(['media_collection_id', 'workspace_member_id'], 'media_collection_member_collection_member_uq');
                $table->index(['workspace_id', 'workspace_member_id'], 'media_collection_member_ws_member_idx');
            });
        }

        if (Schema::hasTable('media_asset_versions')) {
            Schema::table('media_asset_versions', function (Blueprint $table): void {
                if (! Schema::hasColumn('media_asset_versions', 'binary_disk')) $table->string('binary_disk', 40)->nullable()->after('version_number');
                if (! Schema::hasColumn('media_asset_versions', 'binary_path')) $table->string('binary_path', 1000)->nullable()->after('binary_disk');
                if (! Schema::hasColumn('media_asset_versions', 'original_name')) $table->string('original_name', 255)->nullable()->after('binary_path');
                if (! Schema::hasColumn('media_asset_versions', 'mime_type')) $table->string('mime_type', 160)->nullable()->after('original_name');
                if (! Schema::hasColumn('media_asset_versions', 'extension')) $table->string('extension', 20)->nullable()->after('mime_type');
                if (! Schema::hasColumn('media_asset_versions', 'size_bytes')) $table->unsignedBigInteger('size_bytes')->nullable()->after('extension');
                if (! Schema::hasColumn('media_asset_versions', 'checksum_sha256')) $table->char('checksum_sha256', 64)->nullable()->after('size_bytes');
                if (! Schema::hasColumn('media_asset_versions', 'width')) $table->unsignedInteger('width')->nullable()->after('checksum_sha256');
                if (! Schema::hasColumn('media_asset_versions', 'height')) $table->unsignedInteger('height')->nullable()->after('width');
                if (! Schema::hasColumn('media_asset_versions', 'duration_ms')) $table->unsignedInteger('duration_ms')->nullable()->after('height');
                if (! Schema::hasColumn('media_asset_versions', 'binary_available')) $table->boolean('binary_available')->default(false)->after('duration_ms');
                if (! Schema::hasColumn('media_asset_versions', 'binary_status')) $table->string('binary_status', 20)->nullable()->after('binary_available');
                if (! Schema::hasColumn('media_asset_versions', 'copyright_owner')) $table->string('copyright_owner', 180)->nullable()->after('caption');
                if (! Schema::hasColumn('media_asset_versions', 'license_type')) $table->string('license_type', 80)->nullable()->after('copyright_owner');
                if (! Schema::hasColumn('media_asset_versions', 'license_reference')) $table->string('license_reference', 255)->nullable()->after('license_type');
                if (! Schema::hasColumn('media_asset_versions', 'license_expires_at')) $table->date('license_expires_at')->nullable()->after('license_reference');
                if (! Schema::hasColumn('media_asset_versions', 'usage_restrictions')) $table->text('usage_restrictions')->nullable()->after('license_expires_at');
                if (! Schema::hasColumn('media_asset_versions', 'rights_review_at')) $table->date('rights_review_at')->nullable()->after('usage_restrictions');
            });
        }

        if (! Schema::hasTable('media_renditions')) {
            Schema::create('media_renditions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
                $table->foreignId('media_asset_version_id')->nullable()->constrained('media_asset_versions')->nullOnDelete();
                $table->char('spec_hash', 40);
                $table->string('fit', 20)->default('contain');
                $table->unsignedInteger('width');
                $table->unsignedInteger('height');
                $table->string('format', 16);
                $table->unsignedTinyInteger('quality')->default(82);
                $table->string('disk', 40)->default('local');
                $table->string('path', 1000);
                $table->string('mime_type', 80);
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->char('checksum_sha256', 64);
                $table->string('status', 20)->default('ready');
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['media_asset_id', 'spec_hash'], 'media_rendition_asset_spec_uq');
                $table->index(['workspace_id', 'media_asset_id', 'created_at'], 'media_rendition_ws_asset_created_idx');
            });
        }

        if (! Schema::hasTable('media_upload_sessions')) {
            Schema::create('media_upload_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
                $table->string('original_name', 255);
                $table->string('mime_type', 160)->nullable();
                $table->string('extension', 20)->nullable();
                $table->unsignedBigInteger('size_bytes');
                $table->unsignedInteger('chunk_size_bytes');
                $table->unsignedInteger('total_chunks');
                $table->json('received_chunks')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamp('expires_at');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'user_id', 'status'], 'media_upload_session_ws_user_status_idx');
                $table->index(['expires_at', 'status'], 'media_upload_session_expiry_status_idx');
            });
        }
    }

    /** Remove only M7 closure additions so earlier DAM migrations remain independently reversible. */
    public function down(): void
    {
        Schema::dropIfExists('media_upload_sessions');
        Schema::dropIfExists('media_renditions');
        Schema::dropIfExists('media_collection_members');
        if (Schema::hasTable('media_collections') && Schema::hasColumn('media_collections', 'visibility')) {
            Schema::table('media_collections', fn (Blueprint $table) => $table->dropColumn('visibility'));
        }
        if (Schema::hasTable('media_asset_versions')) {
            $columns = ['binary_disk','binary_path','original_name','mime_type','extension','size_bytes','checksum_sha256','width','height','duration_ms','binary_available','binary_status','copyright_owner','license_type','license_reference','license_expires_at','usage_restrictions','rights_review_at'];
            Schema::table('media_asset_versions', function (Blueprint $table) use ($columns): void { foreach ($columns as $column) if (Schema::hasColumn('media_asset_versions', $column)) $table->dropColumn($column); });
        }
        if (Schema::hasTable('media_assets')) {
            $columns = ['copyright_owner','license_type','license_reference','license_expires_at','usage_restrictions','rights_review_at'];
            Schema::table('media_assets', function (Blueprint $table) use ($columns): void { foreach ($columns as $column) if (Schema::hasColumn('media_assets', $column)) $table->dropColumn($column); });
        }
    }
};
