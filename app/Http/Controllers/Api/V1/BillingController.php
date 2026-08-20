<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\BillingTransaction;
use App\Models\CommerceProviderConfig;
use App\Models\SubscriptionPlan;
use App\Services\Billing\BillingUsageService;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\SubscriptionService;
use App\Support\PlanCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/** Provides billing controller behavior within the WorkIntel application. */ class BillingController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(
        private readonly EntitlementService $entitlements,
        private readonly BillingUsageService $usage,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /** Handles the overview operation for the current WorkIntel workflow. */ public function overview(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        if (! SubscriptionPlan::query()->exists()) PlanCatalog::sync();
        $subscription = $this->subscriptions->ensureDefault($workspace)->load(['plan.entitlements']);
        $plans = SubscriptionPlan::query()->with('entitlements')->where('is_active', true)->where('is_public', true)->orderBy('sort_order')->get();
        $invoices = BillingInvoice::query()->with('lines')->where('workspace_id', $workspace->id)->latest('issued_at')->limit(30)->get();
        $transactions = BillingTransaction::query()->where('workspace_id', $workspace->id)->latest('processed_at')->limit(30)->get();

        $flatEntitlements = $this->entitlements->map($workspace);
        // Keep dotted keys for the existing React billing UI while also exposing
        // a nested representation so API consumers can address feature.payroll
        // through normal JSON-path semantics.
        $entitlements = array_merge($flatEntitlements, Arr::undot($flatEntitlements));

        return response()->json([
            'subscription' => $this->subscriptionPayload($subscription),
            'plans' => $plans->map(fn ($plan) => $this->planPayload($plan))->values(),
            'entitlements' => $entitlements,
            'usage' => $this->usage->withLimits($workspace, $this->entitlements),
            'invoices' => $invoices->map(fn ($invoice) => $this->invoicePayload($invoice))->values(),
            'transactions' => $transactions,
            'billing_provider' => config('workintel.billing.provider', 'manual'),
            'commerce_providers' => CommerceProviderConfig::query()->where('enabled', true)->orderByDesc('is_default')->get()->map(fn ($provider) => ['provider'=>$provider->provider,'display_name'=>$provider->display_name,'is_default'=>(bool)$provider->is_default,'health_status'=>$provider->health_status])->values(),
            'can_mark_manual_paid' => (bool) config('workintel.billing.allow_manual_settlement', false),
            'currency_note' => 'Plan prices are currently billed in USD. Workspace payroll/project currencies remain independent.',
        ]);
    }

    /** Handles the change plan operation for the current WorkIntel workflow. */ public function changePlan(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'plan_slug' => ['required', 'string', Rule::exists('subscription_plans', 'slug')->where('is_active', true)],
            'billing_interval' => ['required', Rule::in(['monthly','annual'])],
            'use_trial' => ['sometimes', 'boolean'],
        ]);
        $subscription = $this->subscriptions->changePlan($workspace, $data['plan_slug'], $data['billing_interval'], (bool) ($data['use_trial'] ?? true));
        return response()->json(['message' => 'Workspace plan updated.', 'subscription' => $this->subscriptionPayload($subscription)]);
    }

    /** Determines whether the cancel condition is satisfied. */ public function cancel(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $subscription = $this->subscriptions->ensureDefault($workspace)->load('plan.entitlements');
        return response()->json(['message' => 'Subscription will downgrade to Free at the end of the current period.', 'subscription' => $this->subscriptionPayload($this->subscriptions->cancelAtPeriodEnd($subscription))]);
    }

    /** Handles the resume operation for the current WorkIntel workflow. */ public function resume(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $subscription = $this->subscriptions->ensureDefault($workspace)->load('plan.entitlements');
        return response()->json(['message' => 'Scheduled cancellation removed.', 'subscription' => $this->subscriptionPayload($this->subscriptions->resume($subscription))]);
    }

    /** Handles the mark invoice paid operation for the current WorkIntel workflow. */ public function markInvoicePaid(Request $request, BillingInvoice $billingInvoice): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($billingInvoice->workspace_id === $workspace->id, 404);
        abort_unless((bool) config('workintel.billing.allow_manual_settlement', false), 403, 'Manual invoice settlement is disabled for workspace users.');
        abort_unless($billingInvoice->provider === 'manual', 422, 'Only manual invoices can be marked paid from this interface. Provider-managed invoices must be updated through their billing adapter/webhook.');
        $data = $request->validate(['reference' => ['nullable','string','max:160']]);
        $invoice = $this->subscriptions->markInvoicePaid($billingInvoice, $data['reference'] ?? null);
        return response()->json(['message' => 'Invoice marked paid.', 'invoice' => $this->invoicePayload($invoice->load('lines'))]);
    }

    /** Handles the plan payload operation for the current WorkIntel workflow. */ private function planPayload(SubscriptionPlan $plan): array
    {
        $entitlements = $plan->entitlements->mapWithKeys(fn ($item) => [$item->key => $item->resolvedValue()])->all();
        return [
            'id'=>$plan->id,'name'=>$plan->name,'slug'=>$plan->slug,'description'=>$plan->description,'currency'=>$plan->currency,
            'monthly_price_per_seat'=>(float)$plan->monthly_price_per_seat,'annual_price_per_seat'=>(float)$plan->annual_price_per_seat,
            'trial_days'=>$plan->trial_days,'is_popular'=>$plan->is_popular,'entitlements'=>$entitlements,
        ];
    }

    /** Handles the subscription payload operation for the current WorkIntel workflow. */ private function subscriptionPayload($subscription): array
    {
        $subscription->loadMissing('plan.entitlements');
        return [
            'id'=>$subscription->id,'uuid'=>$subscription->uuid,'status'=>$subscription->status,'billing_interval'=>$subscription->billing_interval,
            'provider'=>$subscription->provider,'seat_quantity'=>$subscription->seat_quantity,'trial_ends_at'=>$subscription->trial_ends_at?->toIso8601String(),
            'current_period_start'=>$subscription->current_period_start?->toIso8601String(),'current_period_end'=>$subscription->current_period_end?->toIso8601String(),
            'cancel_at_period_end'=>$subscription->cancel_at_period_end,'grace_ends_at'=>$subscription->grace_ends_at?->toIso8601String(),
            'grandfathered'=>(bool) (($subscription->provider_metadata ?? [])['grandfathered'] ?? false),
            'plan'=>$this->planPayload($subscription->plan),
        ];
    }

    /** Handles the invoice payload operation for the current WorkIntel workflow. */ private function invoicePayload(BillingInvoice $invoice): array
    {
        return [
            'id'=>$invoice->id,'uuid'=>$invoice->uuid,'number'=>$invoice->number,'status'=>$invoice->status,'currency'=>$invoice->currency,
            'subtotal'=>(float)$invoice->subtotal,'tax_total'=>(float)$invoice->tax_total,'discount_total'=>(float)$invoice->discount_total,
            'total'=>(float)$invoice->total,'amount_paid'=>(float)$invoice->amount_paid,'amount_due'=>(float)$invoice->amount_due,
            'issued_at'=>$invoice->issued_at?->toIso8601String(),'due_at'=>$invoice->due_at?->toIso8601String(),'paid_at'=>$invoice->paid_at?->toIso8601String(),
            'provider'=>$invoice->provider,'provider_hosted_url'=>$invoice->provider_hosted_url,
            'lines'=>$invoice->relationLoaded('lines')?$invoice->lines:[],
        ];
    }
}
