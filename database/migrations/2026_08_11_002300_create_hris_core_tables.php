<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        foreach ([
            ['HRIS', 'hris.view_own'],
            ['HRIS', 'hris.view_team'],
            ['HRIS', 'hris.view_all'],
            ['HRIS', 'hris.manage'],
            ['HRIS', 'hris.documents.manage'],
            ['HRIS', 'hris.assets.manage'],
            ['HRIS', 'hris.policies.manage'],
            ['HRIS', 'hris.lifecycle.manage'],
        ] as [$group, $slug]) {
            if (Schema::hasTable('permissions')) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
                );
            }
        }

        if (Schema::hasTable('roles') && Schema::hasTable('permissions') && Schema::hasTable('role_permissions')) {
            $permissionIds = DB::table('permissions')->pluck('id', 'slug');
            $hasTimestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');
            $grant = function (string $roleSlug, array $slugs) use ($permissionIds, $hasTimestamps): void {
                foreach (DB::table('roles')->where('is_system', true)->where('slug', $roleSlug)->get(['id']) as $role) {
                    foreach ($slugs as $slug) {
                        $permissionId = $permissionIds[$slug] ?? null;
                        if (! $permissionId) continue;
                        $row = ['role_id' => $role->id, 'permission_id' => $permissionId];
                        if ($hasTimestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                        DB::table('role_permissions')->insertOrIgnore($row);
                    }
                }
            };

            $all = ['hris.view_own','hris.view_team','hris.view_all','hris.manage','hris.documents.manage','hris.assets.manage','hris.policies.manage','hris.lifecycle.manage'];
            foreach (['owner', 'admin'] as $role) $grant($role, $all);
            $grant('hr', $all);
            $grant('manager', ['hris.view_own', 'hris.view_team']);
            $grant('team-lead', ['hris.view_own', 'hris.view_team']);
            $grant('payroll-manager', ['hris.view_own']);
            $grant('employee', ['hris.view_own']);
        }

        if (Schema::hasTable('workspace_members')) {
            if (! Schema::hasColumn('workspace_members', 'employment_stage')) Schema::table('workspace_members', fn (Blueprint $table) => $table->string('employment_stage', 30)->default('active')->after('employment_type'));
            if (! Schema::hasColumn('workspace_members', 'probation_end_date')) Schema::table('workspace_members', fn (Blueprint $table) => $table->date('probation_end_date')->nullable()->after('joining_date'));
            if (! Schema::hasColumn('workspace_members', 'termination_date')) Schema::table('workspace_members', fn (Blueprint $table) => $table->date('termination_date')->nullable()->after('probation_end_date'));
        }

        if (! Schema::hasTable('employee_document_folders')) {
            Schema::create('employee_document_folders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('category', 50)->default('general');
                $table->timestamps();
                $table->unique(['workspace_id','member_id','name'], 'edf_ws_member_name_uq');
            });
        }

        if (! Schema::hasTable('employee_documents')) {
            Schema::create('employee_documents', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('folder_id')->nullable()->constrained('employee_document_folders')->nullOnDelete();
                $table->string('title', 180);
                $table->string('document_type', 60)->default('general');
                $table->string('file_name', 255);
                $table->string('storage_path', 500);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->char('sha256', 64)->nullable();
                $table->date('expires_on')->nullable();
                $table->string('visibility', 20)->default('private');
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id','member_id','document_type'], 'edoc_ws_member_type_idx');
                $table->index(['workspace_id','expires_on'], 'edoc_ws_expiry_idx');
            });
        }

        if (! Schema::hasTable('employment_contracts')) {
            Schema::create('employment_contracts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('previous_contract_id')->nullable()->constrained('employment_contracts')->nullOnDelete();
                $table->unsignedSmallInteger('version')->default(1);
                $table->string('title', 180);
                $table->string('contract_type', 40)->default('employment');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('status', 24)->default('draft');
                $table->decimal('salary_amount', 14, 2)->nullable();
                $table->string('salary_currency', 3)->nullable();
                $table->string('salary_period', 20)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('document_id')->nullable()->constrained('employee_documents')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['workspace_id','member_id','version'], 'econ_ws_member_version_uq');
                $table->index(['workspace_id','member_id','status'], 'econ_ws_member_status_idx');
            });
        }

        if (! Schema::hasTable('employee_emergency_contacts')) {
            Schema::create('employee_emergency_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('relationship', 60);
                $table->string('phone', 60);
                $table->string('alternate_phone', 60)->nullable();
                $table->string('email', 180)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
                $table->index(['workspace_id','member_id'], 'eec_ws_member_idx');
            });
        }

        if (! Schema::hasTable('employee_dependents')) {
            Schema::create('employee_dependents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('relationship', 60);
                $table->date('date_of_birth')->nullable();
                $table->boolean('benefits_eligible')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['workspace_id','member_id'], 'edep_ws_member_idx');
            });
        }

        if (! Schema::hasTable('employee_custom_fields')) {
            Schema::create('employee_custom_fields', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('label', 120);
                $table->string('key', 120);
                $table->string('field_type', 30)->default('text');
                $table->json('options')->nullable();
                $table->string('visibility', 20)->default('hr');
                $table->boolean('required')->default(false);
                $table->boolean('active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->timestamps();
                $table->unique(['workspace_id','key'], 'ecf_ws_key_uq');
            });
        }

        if (! Schema::hasTable('employee_custom_values')) {
            Schema::create('employee_custom_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('custom_field_id')->constrained('employee_custom_fields')->cascadeOnDelete();
                $table->text('value')->nullable();
                $table->timestamps();
                $table->unique(['member_id','custom_field_id'], 'ecv_member_field_uq');
            });
        }

        if (! Schema::hasTable('lifecycle_checklist_templates')) {
            Schema::create('lifecycle_checklist_templates', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 140);
                $table->string('type', 30);
                $table->string('status', 20)->default('active');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['workspace_id','name','type'], 'lct_ws_name_type_uq');
            });
        }

        if (! Schema::hasTable('lifecycle_checklist_template_items')) {
            Schema::create('lifecycle_checklist_template_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->constrained('lifecycle_checklist_templates')->cascadeOnDelete();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->string('owner_type', 30)->default('hr');
                $table->integer('due_offset_days')->default(0);
                $table->boolean('required')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employee_lifecycle_checklists')) {
            Schema::create('employee_lifecycle_checklists', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('lifecycle_checklist_templates')->nullOnDelete();
                $table->string('type', 30);
                $table->string('name', 140);
                $table->date('effective_date');
                $table->string('status', 20)->default('active');
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id','member_id','status'], 'elc_ws_member_status_idx');
            });
        }

        if (! Schema::hasTable('employee_lifecycle_checklist_items')) {
            Schema::create('employee_lifecycle_checklist_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('checklist_id')->constrained('employee_lifecycle_checklists')->cascadeOnDelete();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->string('owner_type', 30)->default('hr');
                $table->date('due_date')->nullable();
                $table->string('status', 20)->default('pending');
                $table->boolean('required')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->text('completion_note')->nullable();
                $table->timestamps();
                $table->index(['checklist_id','status'], 'elci_checklist_status_idx');
            });
        }

        if (! Schema::hasTable('company_assets')) {
            Schema::create('company_assets', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('asset_tag', 80);
                $table->string('name', 140);
                $table->string('category', 60);
                $table->string('serial_number', 120)->nullable();
                $table->string('status', 24)->default('available');
                $table->date('purchased_on')->nullable();
                $table->decimal('purchase_cost', 14, 2)->nullable();
                $table->string('currency', 3)->nullable();
                $table->date('warranty_expires_on')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id','asset_tag'], 'asset_ws_tag_uq');
                $table->index(['workspace_id','status','category'], 'asset_ws_status_category_idx');
            });
        }

        if (! Schema::hasTable('asset_assignments')) {
            Schema::create('asset_assignments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('company_assets')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->date('assigned_on');
                $table->date('expected_return_on')->nullable();
                $table->date('returned_on')->nullable();
                $table->string('condition_out', 60)->nullable();
                $table->string('condition_in', 60)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id','member_id','returned_on'], 'aa_ws_member_returned_idx');
                $table->index(['asset_id','returned_on'], 'aa_asset_returned_idx');
            });
        }

        if (! Schema::hasTable('company_policies')) {
            Schema::create('company_policies', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('policy_key', 120);
                $table->unsignedSmallInteger('version')->default(1);
                $table->string('title', 180);
                $table->longText('content');
                $table->string('status', 20)->default('draft');
                $table->boolean('acknowledgement_required')->default(true);
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['workspace_id','policy_key','version'], 'cpol_ws_key_version_uq');
                $table->index(['workspace_id','status','published_at'], 'cpol_ws_status_published_idx');
            });
        }

        if (! Schema::hasTable('policy_acknowledgements')) {
            Schema::create('policy_acknowledgements', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('policy_id')->constrained('company_policies')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('signed_name', 180);
                $table->timestamp('acknowledged_at');
                $table->ipAddress('ip_address')->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->char('content_hash', 64);
                $table->timestamps();
                $table->unique(['policy_id','member_id'], 'pack_policy_member_uq');
            });
        }

        if (! Schema::hasTable('employment_history')) {
            Schema::create('employment_history', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('event_type', 50);
                $table->date('effective_date');
                $table->string('from_value', 180)->nullable();
                $table->string('to_value', 180)->nullable();
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id','member_id','effective_date'], 'eh_ws_member_effective_idx');
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('employment_history');
        Schema::dropIfExists('policy_acknowledgements');
        Schema::dropIfExists('company_policies');
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('company_assets');
        Schema::dropIfExists('employee_lifecycle_checklist_items');
        Schema::dropIfExists('employee_lifecycle_checklists');
        Schema::dropIfExists('lifecycle_checklist_template_items');
        Schema::dropIfExists('lifecycle_checklist_templates');
        Schema::dropIfExists('employee_custom_values');
        Schema::dropIfExists('employee_custom_fields');
        Schema::dropIfExists('employee_dependents');
        Schema::dropIfExists('employee_emergency_contacts');
        Schema::dropIfExists('employment_contracts');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_document_folders');
    }
};
