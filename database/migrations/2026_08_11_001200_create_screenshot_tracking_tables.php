<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('screenshot_settings')) {
            Schema::create('screenshot_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
                $table->boolean('enabled')->default(false);
                $table->unsignedSmallInteger('interval_minutes')->default(10);
                $table->unsignedSmallInteger('randomize_minutes')->default(2);
                $table->boolean('capture_all_monitors')->default(false);
                $table->boolean('blur_by_default')->default(false);
                $table->string('quality', 16)->default('medium');
                $table->boolean('allow_employee_delete')->default(false);
                $table->unsignedSmallInteger('retention_days')->default(90);
                $table->unsignedInteger('max_upload_kb')->default(4096);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('screenshots')) {
            Schema::create('screenshots', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
                $table->string('disk', 32)->default('local');
                $table->string('path', 1024);
                $table->string('mime_type', 80)->default('image/jpeg');
                $table->unsignedInteger('size_bytes')->default(0);
                $table->unsignedSmallInteger('width')->nullable();
                $table->unsignedSmallInteger('height')->nullable();
                $table->unsignedSmallInteger('monitor_index')->default(1);
                $table->string('app_name', 180)->nullable();
                $table->unsignedSmallInteger('activity_percent')->nullable();
                $table->boolean('blurred')->default(false);
                $table->boolean('flagged')->default(false);
                $table->string('flag_reason', 255)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('captured_at');
                $table->timestamp('deleted_at')->nullable();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'captured_at']);
                $table->index(['workspace_id', 'member_id', 'captured_at']);
                $table->index(['device_id', 'captured_at']);
            });
        }

        foreach ([
            ['Screenshots', 'screenshots.manage'],
            ['Screenshots', 'screenshots.settings_manage'],
        ] as [$group, $slug]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('slug', ['screenshots.manage', 'screenshots.settings_manage'])->pluck('id');
        DB::table('roles')->whereIn('slug', ['owner', 'admin'])->get()->each(function ($role) use ($permissionIds) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('screenshots');
        Schema::dropIfExists('screenshot_settings');
    }
};
