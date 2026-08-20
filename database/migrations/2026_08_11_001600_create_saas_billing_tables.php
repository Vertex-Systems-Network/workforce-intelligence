<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('subscription_plans')) {
            Schema::create('subscription_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80);
                $table->string('slug', 40)->unique();
                $table->text('description')->nullable();
                $table->char('currency', 3)->default('USD');
                $table->decimal('monthly_price_per_seat', 10, 2)->default(0);
                $table->decimal('annual_price_per_seat', 10, 2)->default(0);
                $table->unsignedSmallInteger('trial_days')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_public')->default(true);
                $table->boolean('is_popular')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('plan_entitlements')) {
            Schema::create('plan_entitlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
                $table->string('key', 100);
                $table->string('value_type', 16)->default('boolean');
                $table->json('value');
                $table->string('label', 120)->nullable();
                $table->timestamps();
                $table->unique(['subscription_plan_id', 'key']);
            });
        }

        if (! Schema::hasTable('workspace_subscriptions')) {
            Schema::create('workspace_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete()->unique();
                $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
                $table->string('status', 24)->default('active');
                $table->string('billing_interval', 12)->default('monthly');
                $table->string('provider', 32)->default('manual');
                $table->string('provider_customer_id', 160)->nullable();
                $table->string('provider_subscription_id', 160)->nullable();
                $table->unsignedInteger('seat_quantity')->default(1);
                $table->timestamp('trial_started_at')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('current_period_start')->nullable();
                $table->timestamp('current_period_end')->nullable();
                $table->boolean('cancel_at_period_end')->default(false);
                $table->timestamp('canceled_at')->nullable();
                $table->timestamp('grace_ends_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->json('provider_metadata')->nullable();
                $table->timestamps();
                $table->index(['status', 'current_period_end']);
            });
        }

        if (! Schema::hasTable('billing_invoices')) {
            Schema::create('billing_invoices', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_subscription_id')->nullable()->constrained()->nullOnDelete();
                $table->string('number', 48)->unique();
                $table->string('status', 24)->default('open');
                $table->char('currency', 3)->default('USD');
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('tax_total', 12, 2)->default(0);
                $table->decimal('discount_total', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->decimal('amount_paid', 12, 2)->default(0);
                $table->decimal('amount_due', 12, 2)->default(0);
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->string('provider', 32)->default('manual');
                $table->string('provider_invoice_id', 160)->nullable();
                $table->string('provider_hosted_url', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'status', 'issued_at']);
            });
        }

        if (! Schema::hasTable('billing_invoice_lines')) {
            Schema::create('billing_invoice_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('billing_invoice_id')->constrained()->cascadeOnDelete();
                $table->string('description', 255);
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('unit_amount', 12, 2)->default(0);
                $table->decimal('amount', 12, 2)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('billing_transactions')) {
            Schema::create('billing_transactions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('billing_invoice_id')->nullable()->constrained()->nullOnDelete();
                $table->string('provider', 32)->default('manual');
                $table->string('type', 24)->default('payment');
                $table->string('status', 24)->default('pending');
                $table->char('currency', 3)->default('USD');
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('provider_transaction_id', 160)->nullable();
                $table->text('failure_message')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'status', 'created_at']);
            });
        }

        if (! Schema::hasTable('billing_usage_counters')) {
            Schema::create('billing_usage_counters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('metric', 80);
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('quantity', 18, 4)->default(0);
                $table->timestamps();
                $table->unique(['workspace_id', 'metric', 'period_start', 'period_end'], 'billing_usage_unique');
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('billing_usage_counters');
        Schema::dropIfExists('billing_transactions');
        Schema::dropIfExists('billing_invoice_lines');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('workspace_subscriptions');
        Schema::dropIfExists('plan_entitlements');
        Schema::dropIfExists('subscription_plans');
    }
};
