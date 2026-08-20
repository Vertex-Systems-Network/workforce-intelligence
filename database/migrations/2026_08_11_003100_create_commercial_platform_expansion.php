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
            ['Platform', 'platform.view'],
            ['Platform', 'platform.manage'],
            ['Platform', 'platform.branding.manage'],
            ['Platform', 'platform.partner.manage'],
            ['Platform', 'platform.addons.manage'],
            ['Platform', 'platform.imports.manage'],
            ['Platform', 'platform.sandboxes.manage'],
        ] as [$group, $slug]) {
            if (Schema::hasTable('permissions')) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
                );
            }
        }

        if (Schema::hasTable('workspaces')) {
            if (! Schema::hasColumn('workspaces', 'workspace_type')) Schema::table('workspaces', fn (Blueprint $t) => $t->string('workspace_type', 20)->default('production')->after('status'));
            if (! Schema::hasColumn('workspaces', 'parent_workspace_id')) Schema::table('workspaces', fn (Blueprint $t) => $t->foreignId('parent_workspace_id')->nullable()->after('workspace_type')->constrained('workspaces')->nullOnDelete());
            if (! Schema::hasColumn('workspaces', 'sandbox_expires_at')) Schema::table('workspaces', fn (Blueprint $t) => $t->timestamp('sandbox_expires_at')->nullable()->after('parent_workspace_id'));
        }

        if (! Schema::hasTable('workspace_brandings')) {
            Schema::create('workspace_brandings', function (Blueprint $t) {
                $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
                $t->string('product_name', 100)->nullable(); $t->string('support_email', 190)->nullable(); $t->string('support_url', 500)->nullable();
                $t->string('accent_color', 16)->nullable(); $t->string('logo_path', 500)->nullable(); $t->string('logo_mime', 80)->nullable(); $t->string('favicon_path', 500)->nullable(); $t->string('favicon_mime', 80)->nullable();
                $t->boolean('hide_powered_by')->default(false); $t->json('email_branding')->nullable(); $t->timestamps();
            });
        }

        if (! Schema::hasTable('workspace_domains')) {
            Schema::create('workspace_domains', function (Blueprint $t) {
                $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $t->string('hostname', 255)->unique(); $t->string('status', 20)->default('pending'); $t->string('verification_nonce', 64); $t->string('verification_method', 20)->default('dns_txt');
                $t->timestamp('verified_at')->nullable(); $t->timestamp('activated_at')->nullable(); $t->string('certificate_status', 24)->default('pending'); $t->timestamp('last_checked_at')->nullable(); $t->text('last_error')->nullable(); $t->timestamps();
                $t->index(['workspace_id', 'status'], 'wdom_ws_status_idx');
            });
        }

        if (! Schema::hasTable('partner_accounts')) {
            Schema::create('partner_accounts', function (Blueprint $t) {
                $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete(); $t->foreignId('billing_workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
                $t->string('name', 140); $t->string('slug', 100)->unique(); $t->string('type', 24)->default('agency'); $t->string('status', 20)->default('active');
                $t->json('branding')->nullable(); $t->decimal('commission_rate', 7, 4)->default(0); $t->timestamps();
            });
        }

        if (! Schema::hasTable('partner_account_members')) {
            Schema::create('partner_account_members', function (Blueprint $t) {
                $t->id(); $t->foreignId('partner_account_id')->constrained()->cascadeOnDelete(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('role', 24)->default('member'); $t->string('status', 20)->default('active'); $t->timestamps();
                $t->unique(['partner_account_id', 'user_id'], 'pam_account_user_uq');
            });
        }

        if (! Schema::hasTable('partner_workspaces')) {
            Schema::create('partner_workspaces', function (Blueprint $t) {
                $t->id(); $t->foreignId('partner_account_id')->constrained()->cascadeOnDelete(); $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $t->string('relationship_type', 24)->default('managed'); $t->string('external_reference', 120)->nullable(); $t->string('status', 20)->default('active'); $t->timestamps();
                $t->unique(['partner_account_id', 'workspace_id'], 'pw_account_workspace_uq');
                $t->index(['partner_account_id', 'status'], 'pw_account_status_idx');
            });
        }

        if (! Schema::hasTable('partner_api_keys')) {
            Schema::create('partner_api_keys', function (Blueprint $t) {
                $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('partner_account_id')->constrained()->cascadeOnDelete(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->string('name', 120); $t->string('prefix', 18); $t->char('token_hash', 64)->unique(); $t->json('scopes'); $t->timestamp('last_used_at')->nullable(); $t->ipAddress('last_used_ip')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamp('revoked_at')->nullable(); $t->timestamp('created_at')->useCurrent();
                $t->index(['partner_account_id', 'revoked_at'], 'pak_account_revoked_idx');
            });
        }

        if (! Schema::hasTable('platform_addons')) {
            Schema::create('platform_addons', function (Blueprint $t) {
                $t->id(); $t->uuid('uuid')->unique(); $t->string('name', 120); $t->string('slug', 80)->unique(); $t->text('description')->nullable(); $t->string('category', 40)->default('general'); $t->string('status', 20)->default('active');
                $t->string('pricing_mode', 20)->default('flat'); $t->char('currency', 3)->default('USD'); $t->decimal('monthly_price', 12, 2)->default(0); $t->decimal('unit_price', 12, 4)->default(0); $t->decimal('included_quantity', 18, 4)->default(0); $t->string('unit_name', 60)->nullable();
                $t->string('entitlement_key', 120)->nullable(); $t->json('entitlement_value')->nullable(); $t->string('entitlement_mode', 20)->default('grant'); $t->json('eligible_plans')->nullable(); $t->timestamps();
            });
        }

        if (! Schema::hasTable('workspace_addons')) {
            Schema::create('workspace_addons', function (Blueprint $t) {
                $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('workspace_id')->constrained()->cascadeOnDelete(); $t->foreignId('platform_addon_id')->constrained()->restrictOnDelete();
                $t->string('status', 20)->default('active'); $t->decimal('quantity', 12, 4)->default(1); $t->timestamp('started_at')->useCurrent(); $t->timestamp('current_period_start')->nullable(); $t->timestamp('current_period_end')->nullable(); $t->timestamp('canceled_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
                $t->unique(['workspace_id', 'platform_addon_id'], 'wa_ws_addon_uq'); $t->index(['workspace_id', 'status'], 'wa_ws_status_idx');
            });
        }

        if (! Schema::hasTable('addon_usage_events')) {
            Schema::create('addon_usage_events', function (Blueprint $t) {
                $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('workspace_id')->constrained()->cascadeOnDelete(); $t->foreignId('workspace_addon_id')->constrained()->cascadeOnDelete();
                $t->string('metric', 80); $t->decimal('quantity', 18, 4); $t->string('idempotency_key', 160); $t->timestamp('occurred_at'); $t->json('metadata')->nullable(); $t->timestamp('created_at')->useCurrent();
                $t->unique(['workspace_addon_id', 'idempotency_key'], 'aue_addon_idem_uq'); $t->index(['workspace_id', 'occurred_at'], 'aue_ws_occurred_idx');
            });
        }

        if (! Schema::hasTable('industry_templates')) {
            Schema::create('industry_templates', function (Blueprint $t) {
                $t->id(); $t->uuid('uuid')->unique(); $t->string('name', 120); $t->string('slug', 80)->unique(); $t->string('industry', 80); $t->text('description')->nullable(); $t->unsignedSmallInteger('version')->default(1); $t->string('status', 20)->default('active'); $t->json('blueprint'); $t->timestamps();
            });
        }

        if (! Schema::hasTable('industry_template_installations')) {
            Schema::create('industry_template_installations', function (Blueprint $t) {
                $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('workspace_id')->constrained()->cascadeOnDelete(); $t->foreignId('industry_template_id')->constrained()->restrictOnDelete();
                $t->unsignedSmallInteger('template_version'); $t->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('installed_at'); $t->json('summary')->nullable();
                $t->index(['workspace_id', 'installed_at'], 'iti_ws_installed_idx');
            });
        }

        if (! Schema::hasTable('data_import_jobs')) {
            Schema::create('data_import_jobs', function (Blueprint $t) {
                $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('workspace_id')->constrained()->cascadeOnDelete(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->string('source_system', 60)->default('csv'); $t->string('entity_type', 40); $t->string('status', 20)->default('uploaded'); $t->string('file_path', 500); $t->string('original_name', 255); $t->char('file_sha256', 64); $t->json('column_mapping')->nullable(); $t->json('options')->nullable(); $t->json('summary')->nullable(); $t->text('last_error')->nullable(); $t->timestamp('started_at')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps();
                $t->index(['workspace_id', 'status', 'created_at'], 'dij_ws_status_created_idx');
            });
        }

        if (! Schema::hasTable('data_import_items')) {
            Schema::create('data_import_items', function (Blueprint $t) {
                $t->id(); $t->foreignId('data_import_job_id')->constrained()->cascadeOnDelete(); $t->unsignedInteger('row_number'); $t->string('external_key', 190)->nullable(); $t->char('fingerprint', 64); $t->string('status', 20)->default('pending'); $t->string('target_type', 80)->nullable(); $t->unsignedBigInteger('target_id')->nullable(); $t->json('source_data'); $t->json('normalized_data')->nullable(); $t->text('error')->nullable(); $t->timestamps();
                $t->unique(['data_import_job_id', 'row_number'], 'dii_job_row_uq'); $t->index(['data_import_job_id', 'status'], 'dii_job_status_idx');
            });
        }

        $this->grantPlatformPermissions();
        $this->syncPlanEntitlements();
    }

    /** Handles the grant platform permissions operation for the current WorkIntel workflow. */ private function grantPlatformPermissions(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions') || ! Schema::hasTable('permissions')) return;
        $ids = DB::table('permissions')->where('group', 'Platform')->pluck('id');
        if ($ids->isEmpty()) return;
        $timestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');
        foreach (DB::table('roles')->where('is_system', true)->whereIn('slug', ['owner', 'admin'])->get(['id']) as $role) {
            foreach ($ids as $pid) {
                $row = ['role_id' => $role->id, 'permission_id' => $pid]; if ($timestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                DB::table('role_permissions')->insertOrIgnore($row);
            }
        }
    }

    /** Synchronizes sync plan entitlements data with the current application state. */ private function syncPlanEntitlements(): void
    {
        if (! Schema::hasTable('subscription_plans') || ! Schema::hasTable('plan_entitlements')) return;
        $matrix = [
            'free' => ['feature.addon_marketplace'=>true,'feature.import_wizard'=>false,'feature.sandbox_workspace'=>false,'feature.white_label'=>false,'feature.custom_domains'=>false,'feature.partner_platform'=>false,'feature.partner_api'=>false,'limit.sandbox_workspaces'=>0,'limit.custom_domains'=>0,'limit.partner_workspaces'=>0],
            'silver' => ['feature.addon_marketplace'=>true,'feature.import_wizard'=>true,'feature.sandbox_workspace'=>false,'feature.white_label'=>false,'feature.custom_domains'=>false,'feature.partner_platform'=>false,'feature.partner_api'=>false,'limit.sandbox_workspaces'=>0,'limit.custom_domains'=>0,'limit.partner_workspaces'=>0],
            'gold' => ['feature.addon_marketplace'=>true,'feature.import_wizard'=>true,'feature.sandbox_workspace'=>true,'feature.white_label'=>false,'feature.custom_domains'=>false,'feature.partner_platform'=>false,'feature.partner_api'=>false,'limit.sandbox_workspaces'=>1,'limit.custom_domains'=>0,'limit.partner_workspaces'=>0],
            'platinum' => ['feature.addon_marketplace'=>true,'feature.import_wizard'=>true,'feature.sandbox_workspace'=>true,'feature.white_label'=>true,'feature.custom_domains'=>true,'feature.partner_platform'=>true,'feature.partner_api'=>true,'limit.sandbox_workspaces'=>5,'limit.custom_domains'=>3,'limit.partner_workspaces'=>100],
        ];
        foreach ($matrix as $slug => $items) {
            $plan = DB::table('subscription_plans')->where('slug', $slug)->first(); if (! $plan) continue;
            foreach ($items as $key => $value) DB::table('plan_entitlements')->updateOrInsert(
                ['subscription_plan_id' => $plan->id, 'key' => $key],
                ['value_type' => is_bool($value) ? 'boolean' : 'integer', 'value' => json_encode(['value' => $value]), 'label' => ucwords(str_replace(['feature.','limit.','_','.'],['','',' ',' '],$key)), 'updated_at'=>now(), 'created_at'=>now()]
            );
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('data_import_items'); Schema::dropIfExists('data_import_jobs'); Schema::dropIfExists('industry_template_installations'); Schema::dropIfExists('industry_templates');
        Schema::dropIfExists('addon_usage_events'); Schema::dropIfExists('workspace_addons'); Schema::dropIfExists('platform_addons'); Schema::dropIfExists('partner_api_keys'); Schema::dropIfExists('partner_workspaces'); Schema::dropIfExists('partner_account_members'); Schema::dropIfExists('partner_accounts'); Schema::dropIfExists('workspace_domains'); Schema::dropIfExists('workspace_brandings');
    }
};
