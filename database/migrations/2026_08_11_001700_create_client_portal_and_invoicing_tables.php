<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasColumn('clients', 'billing_email')) Schema::table('clients', fn (Blueprint $table) => $table->string('billing_email')->nullable()->after('email'));
        if (! Schema::hasColumn('clients', 'billing_address')) Schema::table('clients', fn (Blueprint $table) => $table->text('billing_address')->nullable()->after('phone'));
        if (! Schema::hasColumn('clients', 'tax_id')) Schema::table('clients', fn (Blueprint $table) => $table->string('tax_id', 80)->nullable()->after('billing_address'));


        if (! Schema::hasColumn('projects', 'client_visible')) Schema::table('projects', fn (Blueprint $table) => $table->boolean('client_visible')->default(true)->after('billable'));
        if (! Schema::hasColumn('tasks', 'client_visible')) Schema::table('tasks', fn (Blueprint $table) => $table->boolean('client_visible')->default(false)->after('billable'));

        if (! Schema::hasTable('client_portal_accounts')) {
            Schema::create('client_portal_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->string('name', 160);
                $table->string('email');
                $table->string('password');
                $table->string('status', 24)->default('pending'); // pending, active, suspended
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'client_id', 'status']);
                $table->unique(['workspace_id', 'email']);
            });
        }

        if (! Schema::hasTable('client_portal_invites')) {
            Schema::create('client_portal_invites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 160);
                $table->string('email');
                $table->char('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'client_id', 'expires_at']);
            });
        }

        if (! Schema::hasTable('client_portal_tokens')) {
            Schema::create('client_portal_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_portal_account_id')->constrained()->cascadeOnDelete();
                $table->char('token_hash', 64)->unique();
                $table->string('name', 80)->default('client-portal');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['client_portal_account_id', 'revoked_at']);
            });
        }

        if (! Schema::hasTable('client_invoices')) {
            Schema::create('client_invoices', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('number', 80);
                $table->string('status', 24)->default('draft'); // draft, sent, partial, paid, overdue, void
                $table->char('currency', 3)->default('USD');
                $table->date('issue_date');
                $table->date('due_date');
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('discount_total', 14, 2)->default(0);
                $table->decimal('tax_percent', 7, 4)->default(0);
                $table->decimal('tax_total', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->decimal('amount_paid', 14, 2)->default(0);
                $table->decimal('amount_due', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->text('terms')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'number']);
                $table->index(['workspace_id', 'client_id', 'status']);
                $table->index(['workspace_id', 'due_date']);
            });
        }

        if (! Schema::hasTable('client_invoice_lines')) {
            Schema::create('client_invoice_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_invoice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->string('description', 500);
                $table->decimal('quantity', 12, 4)->default(1);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('amount', 14, 2)->default(0);
                $table->string('source_type', 40)->default('manual'); // manual, time
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_invoice_time_entries')) {
            Schema::create('client_invoice_time_entries', function (Blueprint $table) {
                $table->foreignId('client_invoice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('time_entry_id')->constrained()->restrictOnDelete();
                $table->decimal('hours', 10, 4);
                $table->decimal('rate', 14, 2);
                $table->decimal('amount', 14, 2);
                $table->timestamps();
                $table->primary(['client_invoice_id', 'time_entry_id']);
                $table->unique('time_entry_id');
            });
        }

        if (! Schema::hasTable('client_payments')) {
            Schema::create('client_payments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_invoice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('amount', 14, 2);
                $table->char('currency', 3);
                $table->string('method', 40)->default('manual');
                $table->string('reference', 160)->nullable();
                $table->date('paid_on');
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'client_id', 'paid_on']);
            });
        }

        if (! Schema::hasTable('client_reports')) {
            Schema::create('client_reports', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 180);
                $table->string('report_type', 40); // project_progress, time_summary, financial_summary
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->json('snapshot');
                $table->text('note')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'client_id', 'published_at']);
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('client_reports');
        Schema::dropIfExists('client_payments');
        Schema::dropIfExists('client_invoice_time_entries');
        Schema::dropIfExists('client_invoice_lines');
        Schema::dropIfExists('client_invoices');
        Schema::dropIfExists('client_portal_tokens');
        Schema::dropIfExists('client_portal_invites');
        Schema::dropIfExists('client_portal_accounts');

        if (Schema::hasColumn('tasks', 'client_visible')) Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('client_visible'));
        if (Schema::hasColumn('projects', 'client_visible')) Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('client_visible'));
        foreach (['billing_email', 'billing_address', 'tax_id'] as $column) {
            if (Schema::hasColumn('clients', $column)) Schema::table('clients', fn (Blueprint $table) => $table->dropColumn($column));
        }
    }
};
