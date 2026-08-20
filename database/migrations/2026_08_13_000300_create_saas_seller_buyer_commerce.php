<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('commerce_provider_configs')) {
            Schema::create('commerce_provider_configs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('provider', 32)->unique();
                $table->string('display_name', 100);
                $table->boolean('enabled')->default(false);
                $table->boolean('is_default')->default(false);
                $table->boolean('test_mode')->default(true);
                $table->longText('credentials')->nullable();
                $table->json('settings')->nullable();
                $table->timestamp('last_tested_at')->nullable();
                $table->string('health_status', 24)->default('unknown');
                $table->text('health_message')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('commerce_coupons')) {
            Schema::create('commerce_coupons', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('code', 64)->unique();
                $table->string('name', 120);
                $table->string('discount_type', 16)->default('percent');
                $table->decimal('discount_value', 12, 2)->default(0);
                $table->char('currency', 3)->nullable();
                $table->json('eligible_plans')->nullable();
                $table->unsignedInteger('max_redemptions')->nullable();
                $table->unsignedInteger('redeemed_count')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('redeem_by')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('commerce_tax_rules')) {
            Schema::create('commerce_tax_rules', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name', 120);
                $table->char('country', 2)->nullable();
                $table->string('state_region', 100)->nullable();
                $table->decimal('rate_percent', 7, 4)->default(0);
                $table->boolean('active')->default(true);
                $table->unsignedSmallInteger('priority')->default(100);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['active', 'country', 'priority'], 'commerce_tax_lookup_idx');
            });
        }

        if (! Schema::hasTable('commerce_checkout_sessions')) {
            Schema::create('commerce_checkout_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
                $table->foreignId('commerce_coupon_id')->nullable()->constrained('commerce_coupons')->nullOnDelete();
                $table->string('billing_interval', 12)->default('monthly');
                $table->string('provider', 32)->default('manual');
                $table->string('status', 24)->default('pending');
                $table->unsignedInteger('seat_quantity')->default(1);
                $table->char('currency', 3)->default('USD');
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('discount_total', 12, 2)->default(0);
                $table->decimal('tax_total', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->string('provider_session_id', 190)->nullable();
                $table->string('checkout_url', 1000)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('canceled_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'status', 'created_at'], 'ccs_ws_status_created_idx');
                $table->index(['provider', 'provider_session_id'], 'ccs_provider_session_idx');
            });
        }

        if (! Schema::hasTable('commerce_coupon_redemptions')) {
            Schema::create('commerce_coupon_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commerce_coupon_id')->constrained('commerce_coupons')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('commerce_checkout_session_id')->nullable()->constrained('commerce_checkout_sessions')->nullOnDelete();
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->timestamp('redeemed_at')->useCurrent();
                $table->unique(['commerce_coupon_id', 'workspace_id'], 'coupon_workspace_unique');
            });
        }

        if (! Schema::hasTable('commerce_refunds')) {
            Schema::create('commerce_refunds', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('billing_invoice_id')->nullable()->constrained('billing_invoices')->nullOnDelete();
                $table->foreignId('billing_transaction_id')->nullable()->constrained('billing_transactions')->nullOnDelete();
                $table->string('provider', 32);
                $table->string('status', 24)->default('pending');
                $table->char('currency', 3)->default('USD');
                $table->decimal('amount', 12, 2);
                $table->string('provider_refund_id', 190)->nullable();
                $table->string('reason', 500)->nullable();
                $table->text('failure_message')->nullable();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('processed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'status', 'created_at'], 'refund_ws_status_idx');
            });
        }

        if (! Schema::hasTable('commerce_webhook_events')) {
            Schema::create('commerce_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 32);
                $table->string('event_id', 190);
                $table->string('event_type', 120)->nullable();
                $table->char('payload_hash', 64);
                $table->string('status', 24)->default('received');
                $table->timestamp('processed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['provider', 'event_id'], 'commerce_webhook_event_unique');
            });
        }

        if (! Schema::hasTable('commerce_dunning_attempts')) {
            Schema::create('commerce_dunning_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_subscription_id')->constrained('workspace_subscriptions')->cascadeOnDelete();
                $table->foreignId('billing_invoice_id')->nullable()->constrained('billing_invoices')->nullOnDelete();
                $table->unsignedSmallInteger('attempt_number')->default(1);
                $table->string('status', 24)->default('scheduled');
                $table->timestamp('attempted_at')->nullable();
                $table->timestamp('next_attempt_at')->nullable();
                $table->text('failure_message')->nullable();
                $table->timestamps();
                $table->unique(['workspace_subscription_id', 'attempt_number'], 'dunning_subscription_attempt_unique');
                $table->index(['status', 'next_attempt_at'], 'dunning_status_next_idx');
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('commerce_dunning_attempts');
        Schema::dropIfExists('commerce_webhook_events');
        Schema::dropIfExists('commerce_refunds');
        Schema::dropIfExists('commerce_coupon_redemptions');
        Schema::dropIfExists('commerce_checkout_sessions');
        Schema::dropIfExists('commerce_tax_rules');
        Schema::dropIfExists('commerce_coupons');
        Schema::dropIfExists('commerce_provider_configs');
    }
};
