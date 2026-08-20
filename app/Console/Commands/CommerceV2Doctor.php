<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use App\Support\PermissionCatalog;
use App\Support\PlanCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Validates Commerce V2 schema, entitlement and permission prerequisites after deployment. */
class CommerceV2Doctor extends Command
{
    protected $signature = 'workintel:commerce-v2-doctor';
    protected $description = 'Validate Seller Platform and workspace client-payment Commerce V2 prerequisites.';

    /** Executes Commerce V2 deployment checks and returns a process-safe status code. */
    public function handle(): int
    {
        $errors = [];
        foreach (['workspace_client_payment_gateways', 'client_payment_checkout_sessions', 'client_invoice_schedules'] as $table) {
            if (! Schema::hasTable($table)) $errors[] = "Missing {$table}.";
        }
        foreach (['allowed_gateways', 'invoice_schedule_id'] as $column) {
            if (Schema::hasTable('client_invoices') && ! Schema::hasColumn('client_invoices', $column)) $errors[] = "client_invoices.{$column} is missing.";
        }
        foreach (['provider', 'provider_transaction_id', 'metadata'] as $column) {
            if (Schema::hasTable('client_payments') && ! Schema::hasColumn('client_payments', $column)) $errors[] = "client_payments.{$column} is missing.";
        }

        $permissions = array_column(PermissionCatalog::ITEMS, 1);
        foreach (['client_payments.manage', 'client_invoices.recurring_manage'] as $permission) {
            if (! in_array($permission, $permissions, true)) $errors[] = "Permission {$permission} is missing from the catalog.";
        }

        $capabilities = array_column(PlanCatalog::capabilities(), 'key');
        foreach (['feature.client_payments', 'feature.recurring_client_invoices'] as $capability) {
            if (! in_array($capability, $capabilities, true)) $errors[] = "Plan capability {$capability} is missing.";
        }

        if (Schema::hasTable('subscription_plans')) {
            foreach (SubscriptionPlan::with('entitlements')->get() as $plan) {
                $keys = $plan->entitlements->pluck('key')->all();
                foreach (['feature.client_payments', 'feature.recurring_client_invoices'] as $capability) {
                    if (! in_array($capability, $keys, true)) $errors[] = "{$plan->slug} plan is missing {$capability}.";
                }
            }
        }

        if ($errors) {
            foreach ($errors as $error) $this->error($error);
            return self::FAILURE;
        }

        $this->info('Commerce V2 Seller Platform + Client Payments: PASS');
        return self::SUCCESS;
    }
}
