<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds recoverable lifecycle fields and the workspace media-library schema. */
    public function up(): void
    {
        foreach (['clients', 'projects', 'tasks'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->softDeletes();
                });
            }
        }

        if (! Schema::hasTable('media_folders')) {
            Schema::create('media_folders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('media_folders')->nullOnDelete();
                $table->string('name', 120);
                $table->string('slug', 140);
                $table->unsignedInteger('sort_order')->default(1000);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();
                $table->index(['workspace_id', 'parent_id', 'sort_order'], 'media_folder_ws_parent_sort_idx');
            });
        }

        if (! Schema::hasTable('media_assets')) {
            Schema::create('media_assets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 180);
                $table->string('original_name', 255);
                $table->string('disk', 40)->default('local');
                $table->string('path', 1000);
                $table->string('mime_type', 160)->nullable();
                $table->string('extension', 20)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->char('checksum_sha256', 64);
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('alt_text', 300)->nullable();
                $table->text('caption')->nullable();
                $table->string('visibility', 20)->default('private');
                $table->string('status', 24)->default('ready');
                $table->json('metadata')->nullable();
                $table->softDeletes();
                $table->timestamps();
                $table->index(['workspace_id', 'deleted_at', 'created_at'], 'media_asset_ws_deleted_created_idx');
                $table->index(['workspace_id', 'checksum_sha256'], 'media_asset_ws_checksum_idx');
                $table->index(['workspace_id', 'mime_type'], 'media_asset_ws_mime_idx');
            });
        }

        if (! Schema::hasTable('media_tags')) {
            Schema::create('media_tags', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 80);
                $table->string('color', 20)->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'name'], 'media_tag_ws_name_uq');
            });
        }

        if (! Schema::hasTable('media_asset_tag')) {
            Schema::create('media_asset_tag', function (Blueprint $table): void {
                $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
                $table->foreignId('media_tag_id')->constrained('media_tags')->cascadeOnDelete();
                $table->primary(['media_asset_id', 'media_tag_id']);
            });
        }

        if (! Schema::hasTable('media_usages')) {
            Schema::create('media_usages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
                $table->string('resource_type', 80);
                $table->unsignedBigInteger('resource_id')->nullable();
                $table->string('field', 80)->nullable();
                $table->string('label', 180)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'resource_type', 'resource_id'], 'media_usage_ws_resource_idx');
                $table->index(['media_asset_id', 'resource_type'], 'media_usage_asset_type_idx');
            });
        }

        if (! Schema::hasTable('data_lifecycle_events')) {
            Schema::create('data_lifecycle_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('resource_type', 60);
                $table->unsignedBigInteger('resource_id');
                $table->string('action', 30);
                $table->json('snapshot')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'resource_type', 'created_at'], 'lifecycle_ws_type_created_idx');
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'avatar_media_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('avatar_media_id')->nullable()->after('avatar_url')->constrained('media_assets')->nullOnDelete();
            });
        }

        $this->syncPermissions();
    }

    /** Removes only the lifecycle/media additions created by this migration. */
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'avatar_media_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('avatar_media_id'));
        }

        Schema::dropIfExists('data_lifecycle_events');
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_asset_tag');
        Schema::dropIfExists('media_tags');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('media_folders');

        foreach (['clients', 'projects', 'tasks'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropSoftDeletes());
            }
        }
    }

    /** Creates missing Block D permissions and grants them to existing fixed administrators. */
    private function syncPermissions(): void
    {
        if (! Schema::hasTable('permissions')) return;

        $items = [
            ['Media', 'media.view', 'Media View'],
            ['Media', 'media.manage', 'Media Manage'],
            ['Lifecycle', 'trash.view', 'Trash View'],
            ['Lifecycle', 'trash.restore', 'Trash Restore'],
            ['Lifecycle', 'trash.purge', 'Trash Purge'],
        ];

        $hasTimestamps = Schema::hasColumn('permissions', 'created_at') && Schema::hasColumn('permissions', 'updated_at');
        foreach ($items as [$group, $slug, $name]) {
            $row = ['slug' => $slug, 'name' => $name, 'group' => $group];
            if ($hasTimestamps) $row += ['created_at' => now(), 'updated_at' => now()];
            DB::table('permissions')->updateOrInsert(['slug' => $slug], $row);
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) return;
        $permissionIds = DB::table('permissions')->whereIn('slug', collect($items)->pluck(1))->pluck('id');
        $pivotTimestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');
        foreach (DB::table('roles')->where('is_system', true)->whereIn('slug', ['owner', 'admin'])->pluck('id') as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $row = ['role_id' => $roleId, 'permission_id' => $permissionId];
                if ($pivotTimestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                DB::table('role_permissions')->insertOrIgnore($row);
            }
        }
    }
};
