<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        foreach ([
            'phone' => fn (Blueprint $t) => $t->string('phone', 40)->nullable()->after('email'),
            'avatar_url' => fn (Blueprint $t) => $t->string('avatar_url', 1000)->nullable()->after('phone'),
            'force_password_change' => fn (Blueprint $t) => $t->boolean('force_password_change')->default(false)->after('status'),
            'password_changed_at' => fn (Blueprint $t) => $t->timestamp('password_changed_at')->nullable()->after('force_password_change'),
        ] as $column => $adder) {
            if (Schema::hasTable('users') && ! Schema::hasColumn('users', $column)) {
                Schema::table('users', $adder);
            }
        }

        if (! Schema::hasTable('workspace_registration_settings')) {
            Schema::create('workspace_registration_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('mode', 30)->default('invite_only');
                $table->string('default_role_slug', 80)->default('employee');
                $table->json('allowed_domains')->nullable();
                $table->boolean('require_email_verification')->default(true);
                $table->unsignedSmallInteger('invite_expires_hours')->default(168);
                $table->boolean('allow_existing_users')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('workspace_invitations')) {
            Schema::create('workspace_invitations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('email')->nullable();
                $table->char('token_hash', 64)->unique();
                $table->string('token_prefix', 18);
                $table->string('role_slug', 80)->default('employee');
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('job_title_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('manager_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('employment_type', 30)->default('full_time');
                $table->timestamp('expires_at');
                $table->timestamp('accepted_at')->nullable();
                $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'expires_at', 'accepted_at'], 'wi_ws_exp_accept_idx');
                $table->index(['workspace_id', 'email'], 'wi_ws_email_idx');
            });
        }

        if (! Schema::hasTable('email_verification_tokens')) {
            Schema::create('email_verification_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->nullable()->constrained('workspace_members')->cascadeOnDelete();
                $table->char('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['user_id', 'expires_at'], 'evt_user_exp_idx');
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('workspace_registration_settings');
        // User columns intentionally remain on rollback: credential/lifecycle metadata can contain production state.
    }
};
