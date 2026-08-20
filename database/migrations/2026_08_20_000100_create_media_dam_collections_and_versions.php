<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds reusable DAM collections, member favorites, metadata versions and focal-point metadata. */
return new class extends Migration
{
    /** Apply additive DAM schema without changing existing file storage. */
    public function up(): void
    {
        if (Schema::hasTable('media_assets')) {
            Schema::table('media_assets', function (Blueprint $table): void {
                if (! Schema::hasColumn('media_assets', 'focal_x')) $table->unsignedTinyInteger('focal_x')->nullable()->after('height');
                if (! Schema::hasColumn('media_assets', 'focal_y')) $table->unsignedTinyInteger('focal_y')->nullable()->after('focal_x');
            });
        }

        if (! Schema::hasTable('media_collections')) {
            Schema::create('media_collections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['workspace_id', 'name'], 'media_collection_ws_name_uq');
                $table->index(['workspace_id', 'updated_at'], 'media_collection_ws_updated_idx');
            });
        }

        if (! Schema::hasTable('media_asset_collection')) {
            Schema::create('media_asset_collection', function (Blueprint $table): void {
                $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
                $table->foreignId('media_collection_id')->constrained('media_collections')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['media_asset_id', 'media_collection_id'], 'media_asset_collection_pk');
            });
        }

        if (! Schema::hasTable('media_favorites')) {
            Schema::create('media_favorites', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['workspace_member_id', 'media_asset_id'], 'media_favorite_member_asset_uq');
                $table->index(['workspace_id', 'workspace_member_id'], 'media_favorite_ws_member_idx');
            });
        }

        if (! Schema::hasTable('media_asset_versions')) {
            Schema::create('media_asset_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
                $table->unsignedInteger('version_number');
                $table->string('name', 180);
                $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
                $table->string('alt_text', 300)->nullable();
                $table->text('caption')->nullable();
                $table->unsignedTinyInteger('focal_x')->nullable();
                $table->unsignedTinyInteger('focal_y')->nullable();
                $table->json('tags')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['media_asset_id', 'version_number'], 'media_asset_version_asset_number_uq');
                $table->index(['workspace_id', 'media_asset_id', 'created_at'], 'media_asset_version_ws_asset_created_idx');
            });
        }

        if (Schema::hasTable('media_assets') && Schema::hasTable('media_asset_versions')) {
            $existing = DB::table('media_asset_versions')->select('media_asset_id')->distinct();
            DB::table('media_assets')->whereNotIn('id', $existing)->orderBy('id')->chunkById(200, function ($assets): void {
                $now = now();
                $rows = [];
                foreach ($assets as $asset) {
                    $rows[] = [
                        'workspace_id' => $asset->workspace_id,
                        'media_asset_id' => $asset->id,
                        'version_number' => 1,
                        'name' => $asset->name,
                        'folder_id' => $asset->folder_id,
                        'alt_text' => $asset->alt_text,
                        'caption' => $asset->caption,
                        'focal_x' => property_exists($asset, 'focal_x') ? $asset->focal_x : null,
                        'focal_y' => property_exists($asset, 'focal_y') ? $asset->focal_y : null,
                        'tags' => null,
                        'metadata' => json_encode(['source' => 'dam_v3_backfill']),
                        'created_by' => $asset->uploaded_by,
                        'created_at' => $asset->created_at ?? $now,
                        'updated_at' => $now,
                    ];
                }
                if ($rows) DB::table('media_asset_versions')->insert($rows);
            }, 'id');
        }
    }

    /** Remove only the additive DAM structures created by this migration. */
    public function down(): void
    {
        Schema::dropIfExists('media_asset_versions');
        Schema::dropIfExists('media_favorites');
        Schema::dropIfExists('media_asset_collection');
        Schema::dropIfExists('media_collections');
        if (Schema::hasTable('media_assets')) {
            Schema::table('media_assets', function (Blueprint $table): void {
                if (Schema::hasColumn('media_assets', 'focal_y')) $table->dropColumn('focal_y');
                if (Schema::hasColumn('media_assets', 'focal_x')) $table->dropColumn('focal_x');
            });
        }
    }
};
