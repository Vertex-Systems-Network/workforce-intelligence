<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (! Schema::hasColumn('roles', 'description')) $table->string('description', 500)->nullable()->after('name');
                if (! Schema::hasColumn('roles', 'status')) $table->string('status', 20)->default('active')->after('is_system');
                if (! Schema::hasColumn('roles', 'template_key')) $table->string('template_key', 80)->nullable()->after('status');
                if (! Schema::hasColumn('roles', 'created_by')) $table->foreignId('created_by')->nullable()->after('template_key')->constrained('users')->nullOnDelete();
                if (! Schema::hasColumn('roles', 'archived_at')) $table->timestamp('archived_at')->nullable()->after('created_by');
            });
        }

        if (Schema::hasTable('member_roles')) {
            Schema::table('member_roles', function (Blueprint $table) {
                if (! Schema::hasColumn('member_roles', 'is_primary')) $table->boolean('is_primary')->default(false);
                if (! Schema::hasColumn('member_roles', 'assigned_by')) $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('role_permission_denies')) {
            Schema::create('role_permission_denies', function (Blueprint $table) {
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['role_id', 'permission_id']);
            });
        }

        if (! Schema::hasTable('role_data_scopes')) {
            Schema::create('role_data_scopes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->string('resource', 50);
                $table->string('scope_type', 30)->default('own');
                $table->json('scope_ids')->nullable();
                $table->timestamps();
                $table->unique(['role_id', 'resource'], 'rds_role_resource_uq');
                $table->index(['resource', 'scope_type'], 'rds_resource_scope_idx');
            });
        }

        if (! Schema::hasTable('role_module_access')) {
            Schema::create('role_module_access', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->string('module_key', 60);
                $table->string('access', 12)->default('inherit');
                $table->timestamps();
                $table->unique(['role_id', 'module_key'], 'rma_role_module_uq');
            });
        }

        // Dedicated access-control permissions. Owner/Admin receive them without changing other roles.
        if (Schema::hasTable('permissions')) {
            foreach ([
                ['name' => 'View Access Control', 'slug' => 'access.view', 'group' => 'Access'],
                ['name' => 'Manage Access Control', 'slug' => 'access.manage', 'group' => 'Access'],
            ] as $permission) {
                DB::table('permissions')->updateOrInsert(['slug' => $permission['slug']], $permission);
            }
        }

        if (Schema::hasTable('roles') && Schema::hasTable('permissions') && Schema::hasTable('role_permissions')) {
            $permissionIds = DB::table('permissions')->whereIn('slug', ['access.view', 'access.manage'])->pluck('id');
            $timestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');
            foreach (DB::table('roles')->where('is_system', true)->whereIn('slug', ['owner', 'admin'])->get(['id']) as $role) {
                foreach ($permissionIds as $permissionId) {
                    $row = ['role_id' => $role->id, 'permission_id' => $permissionId];
                    if ($timestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                    DB::table('role_permissions')->insertOrIgnore($row);
                }
            }
        }

        // Establish a deterministic primary role for existing memberships.
        if (Schema::hasTable('member_roles') && Schema::hasColumn('member_roles', 'is_primary')) {
            $members = DB::table('member_roles')->select('workspace_member_id')->distinct()->pluck('workspace_member_id');
            foreach ($members as $memberId) {
                if (DB::table('member_roles')->where('workspace_member_id', $memberId)->where('is_primary', true)->exists()) continue;
                $roleId = DB::table('member_roles')->where('workspace_member_id', $memberId)->orderBy('role_id')->value('role_id');
                if ($roleId) DB::table('member_roles')->where('workspace_member_id', $memberId)->where('role_id', $roleId)->update(['is_primary' => true]);
            }
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('role_module_access');
        Schema::dropIfExists('role_data_scopes');
        Schema::dropIfExists('role_permission_denies');
        // Additive columns are intentionally retained on rollback to keep production rollback non-destructive.
    }
};
