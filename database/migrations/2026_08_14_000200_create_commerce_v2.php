<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Adds seller capability controls and workspace-owned client payment infrastructure. */
    public function up(): void
    {
        if (! Schema::hasTable('workspace_client_payment_gateways')) {
            Schema::create('workspace_client_payment_gateways', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 32);
                $table->string('display_name', 100);
                $table->boolean('enabled')->default(false);
                $table->boolean('is_default')->default(false);
                $table->boolean('test_mode')->default(true);
                $table->boolean('client_portal_enabled')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->text('credentials')->nullable();
                $table->json('settings')->nullable();
                $table->timestamp('last_tested_at')->nullable();
                $table->string('health_status', 24)->default('untested');
                $table->text('health_message')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['workspace_id', 'provider'], 'client_gateway_workspace_provider_uq');
                $table->index(['workspace_id', 'enabled', 'client_portal_enabled'], 'client_gateway_workspace_enabled_idx');
            });
        }

        if (! Schema::hasTable('client_payment_checkout_sessions')) {
            Schema::create('client_payment_checkout_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_invoice_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('workspace_client_payment_gateway_id')->nullable();
                $table->foreign('workspace_client_payment_gateway_id', 'client_checkout_gateway_fk')
                    ->references('id')->on('workspace_client_payment_gateways')->nullOnDelete();
                $table->string('provider', 32);
                $table->string('status', 24)->default('pending');
                $table->char('currency', 3);
                $table->decimal('amount', 14, 2);
                $table->string('provider_session_id', 190)->nullable();
                $table->string('provider_transaction_id', 190)->nullable();
                $table->text('checkout_url')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('failure_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'status', 'created_at'], 'client_checkout_workspace_status_idx');
                $table->index(['client_invoice_id', 'status'], 'client_checkout_invoice_status_idx');
                $table->index(['provider', 'provider_session_id'], 'client_checkout_provider_session_idx');
            });
        }

        if (! Schema::hasTable('client_invoice_schedules')) {
            Schema::create('client_invoice_schedules', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 160);
                $table->string('status', 20)->default('active');
                $table->string('frequency', 20)->default('monthly');
                $table->unsignedSmallInteger('interval_count')->default(1);
                $table->unsignedSmallInteger('due_days')->default(14);
                $table->char('currency', 3);
                $table->decimal('discount_total', 14, 2)->default(0);
                $table->decimal('tax_percent', 7, 4)->default(0);
                $table->boolean('auto_send')->default(true);
                $table->boolean('include_unbilled_time')->default(false);
                $table->json('project_ids')->nullable();
                $table->json('lines')->nullable();
                $table->json('allowed_gateways')->nullable();
                $table->json('reminder_settings')->nullable();
                $table->text('notes')->nullable();
                $table->text('terms')->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('next_run_at');
                $table->timestamp('last_run_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('paused_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'next_run_at'], 'client_invoice_schedule_due_idx');
                $table->index(['workspace_id', 'client_id'], 'client_invoice_schedule_workspace_client_idx');
            });
        }

        if (Schema::hasTable('client_invoices')) {
            if (! Schema::hasColumn('client_invoices', 'allowed_gateways')) {
                Schema::table('client_invoices', fn (Blueprint $table) => $table->json('allowed_gateways')->nullable()->after('terms'));
            }
            if (! Schema::hasColumn('client_invoices', 'invoice_schedule_id')) {
                Schema::table('client_invoices', fn (Blueprint $table) => $table->foreignId('invoice_schedule_id')->nullable()->after('created_by')->constrained('client_invoice_schedules')->nullOnDelete());
            }
        }

        if (Schema::hasTable('client_payments')) {
            if (! Schema::hasColumn('client_payments', 'provider')) {
                Schema::table('client_payments', fn (Blueprint $table) => $table->string('provider', 32)->nullable()->after('method'));
            }
            if (! Schema::hasColumn('client_payments', 'provider_transaction_id')) {
                Schema::table('client_payments', fn (Blueprint $table) => $table->string('provider_transaction_id', 190)->nullable()->after('reference'));
            }
            if (! Schema::hasColumn('client_payments', 'metadata')) {
                Schema::table('client_payments', fn (Blueprint $table) => $table->json('metadata')->nullable()->after('note'));
            }
            Schema::table('client_payments', fn (Blueprint $table) => $table->unique(['workspace_id', 'provider', 'provider_transaction_id'], 'client_payment_provider_tx_uq'));
        }
    }

    /** Removes Commerce V2 additions while leaving pre-existing billing data untouched. */
    public function down(): void
    {
        if (Schema::hasTable('client_payments')) {
            Schema::table('client_payments', function (Blueprint $table) {
                $table->dropUnique('client_payment_provider_tx_uq');
                foreach (['provider', 'provider_transaction_id', 'metadata'] as $column) {
                    if (Schema::hasColumn('client_payments', $column)) $table->dropColumn($column);
                }
            });
        }
        if (Schema::hasTable('client_invoices')) {
            Schema::table('client_invoices', function (Blueprint $table) {
                if (Schema::hasColumn('client_invoices', 'invoice_schedule_id')) $table->dropConstrainedForeignId('invoice_schedule_id');
                if (Schema::hasColumn('client_invoices', 'allowed_gateways')) $table->dropColumn('allowed_gateways');
            });
        }
        Schema::dropIfExists('client_invoice_schedules');
        Schema::dropIfExists('client_payment_checkout_sessions');
        Schema::dropIfExists('workspace_client_payment_gateways');
    }
};
