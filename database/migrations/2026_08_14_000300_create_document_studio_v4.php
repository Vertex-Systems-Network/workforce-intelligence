<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds Document Studio V4 workflow, sharing, signing, reusable-component, comment and render metadata. */
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            foreach ([
                ['Documents', 'documents.share'],
                ['Documents', 'documents.sign'],
                ['Documents', 'documents.approve'],
                ['Documents', 'documents.components_manage'],
            ] as [$group, $slug]) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
                );
            }
        }

        if (Schema::hasTable('generated_documents')) {
            $columns = [
                'workflow_status' => fn (Blueprint $table) => $table->string('workflow_status', 24)->default('generated')->after('status'),
                'render_driver' => fn (Blueprint $table) => $table->string('render_driver', 32)->nullable()->after('workflow_status'),
                'render_metadata' => fn (Blueprint $table) => $table->json('render_metadata')->nullable()->after('render_driver'),
                'render_context_encrypted' => fn (Blueprint $table) => $table->longText('render_context_encrypted')->nullable()->after('render_metadata'),
                'approved_at' => fn (Blueprint $table) => $table->timestamp('approved_at')->nullable()->after('generated_at'),
                'signed_at' => fn (Blueprint $table) => $table->timestamp('signed_at')->nullable()->after('approved_at'),
                'locked_at' => fn (Blueprint $table) => $table->timestamp('locked_at')->nullable()->after('signed_at'),
            ];
            foreach ($columns as $column => $definition) {
                if (! Schema::hasColumn('generated_documents', $column)) {
                    Schema::table('generated_documents', $definition);
                }
            }
        }

        if (! Schema::hasTable('document_components')) {
            Schema::create('document_components', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 160);
                $table->string('category', 60)->default('content');
                $table->json('content_schema');
                $table->json('settings')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'category'], 'doc_component_ws_category_idx');
            });
        }

        if (! Schema::hasTable('document_share_links')) {
            Schema::create('document_share_links', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('generated_document_id')->constrained('generated_documents')->cascadeOnDelete();
                $table->char('token_hash', 64)->unique();
                $table->string('access_mode', 20)->default('view');
                $table->unsignedInteger('max_views')->nullable();
                $table->unsignedInteger('view_count')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_viewed_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'generated_document_id'], 'doc_share_ws_document_idx');
            });
        }

        if (! Schema::hasTable('document_signature_requests')) {
            Schema::create('document_signature_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('generated_document_id')->constrained('generated_documents')->cascadeOnDelete();
                $table->foreignId('signer_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('signer_name', 160);
                $table->string('signer_email', 255)->nullable();
                $table->string('role_label', 120)->nullable();
                $table->char('token_hash', 64)->unique();
                $table->string('status', 20)->default('pending');
                $table->string('signature_method', 20)->nullable();
                $table->string('typed_name', 160)->nullable();
                $table->longText('signature_data')->nullable();
                $table->char('request_ip_hash', 64)->nullable();
                $table->char('signature_ip_hash', 64)->nullable();
                $table->string('consent_version', 40)->default('v1');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamps();
                $table->index(['generated_document_id', 'status'], 'doc_signature_document_status_idx');
            });
        }

        if (! Schema::hasTable('document_review_events')) {
            Schema::create('document_review_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('generated_document_id')->constrained('generated_documents')->cascadeOnDelete();
                $table->foreignId('actor_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('event', 40);
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['generated_document_id', 'created_at'], 'doc_review_document_created_idx');
            });
        }

        if (! Schema::hasTable('document_comments')) {
            Schema::create('document_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->cascadeOnDelete();
                $table->foreignId('generated_document_id')->nullable()->constrained('generated_documents')->cascadeOnDelete();
                $table->string('block_id', 100)->nullable();
                $table->foreignId('author_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->text('body');
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamps();
                $table->index(['document_template_id', 'resolved_at'], 'doc_comment_template_resolved_idx');
                $table->index(['generated_document_id', 'resolved_at'], 'doc_comment_generated_resolved_idx');
            });
        }

        if (Schema::hasTable('roles') && Schema::hasTable('role_permissions') && Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')->whereIn('slug', [
                'documents.share', 'documents.sign', 'documents.approve', 'documents.components_manage',
            ])->pluck('id', 'slug');
            $hasTimestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');
            $map = [
                'owner' => array_keys($permissionIds->all()),
                'admin' => array_keys($permissionIds->all()),
                'hr' => ['documents.share', 'documents.sign', 'documents.approve'],
                'payroll-manager' => ['documents.share', 'documents.sign', 'documents.approve'],
                'manager' => ['documents.sign'],
                'team-lead' => ['documents.sign'],
            ];
            foreach (DB::table('roles')->where('status', 'active')->get(['id', 'slug']) as $role) {
                foreach ($map[$role->slug] ?? [] as $slug) {
                    $permissionId = $permissionIds[$slug] ?? null;
                    if (! $permissionId) continue;
                    $row = ['role_id' => $role->id, 'permission_id' => $permissionId];
                    if ($hasTimestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                    DB::table('role_permissions')->insertOrIgnore($row);
                }
            }
        }
    }

    /** Removes only Document Studio V4 persistence additions. */
    public function down(): void
    {
        Schema::dropIfExists('document_comments');
        Schema::dropIfExists('document_review_events');
        Schema::dropIfExists('document_signature_requests');
        Schema::dropIfExists('document_share_links');
        Schema::dropIfExists('document_components');

        if (Schema::hasTable('generated_documents')) {
            Schema::table('generated_documents', function (Blueprint $table) {
                foreach (['workflow_status', 'render_driver', 'render_metadata', 'render_context_encrypted', 'approved_at', 'signed_at', 'locked_at'] as $column) {
                    if (Schema::hasColumn('generated_documents', $column)) $table->dropColumn($column);
                }
            });
        }
    }
};
