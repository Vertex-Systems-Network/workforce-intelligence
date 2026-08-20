<?php

namespace App\Services\Billing;

use App\Models\BillingInvoice;
use App\Models\BillingTransaction;
use App\Models\SubscriptionPlan;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\PlanCatalog;
use Illuminate\Validation\ValidationException;

/** Provides subscription service behavior within the WorkIntel application. */ class SubscriptionService
{
    /** Handles the ensure default operation for the current WorkIntel workflow. */ public function ensureDefault(Workspace $workspace, string $planSlug = 'free'): WorkspaceSubscription
    {
        $existing = WorkspaceSubscription::query()->where('workspace_id', $workspace->id)->first();
        if ($existing) {
            $seats = max(1, $workspace->members()->where('status', 'active')->count());
            if ((int) $existing->seat_quantity !== $seats) $existing->update(['seat_quantity' => $seats]);
            return $existing;
        }
        $plan = SubscriptionPlan::query()->where('slug', $planSlug)->where('is_active', true)->first();
        if (! $plan) {
            PlanCatalog::sync();
            $plan = SubscriptionPlan::query()->where('slug', $planSlug)->where('is_active', true)->firstOrFail();
        }
        return WorkspaceSubscription::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $workspace->id, 'subscription_plan_id' => $plan->id,
            'status' => 'active', 'billing_interval' => 'monthly', 'provider' => config('workintel.billing.provider', 'manual'),
            'seat_quantity' => max(1, $workspace->members()->where('status', 'active')->count()), 'current_period_start' => now(),
        ]);
    }

    /** Handles the change plan operation for the current WorkIntel workflow. */ public function changePlan(Workspace $workspace, string $planSlug, string $interval, bool $useTrial = true): WorkspaceSubscription
    {
        $plan = SubscriptionPlan::query()->where('slug', $planSlug)->where('is_active', true)->first();
        if (! $plan) {
            PlanCatalog::sync();
            $plan = SubscriptionPlan::query()->where('slug', $planSlug)->where('is_active', true)->firstOrFail();
        }
        if (! in_array($interval, ['monthly','annual'], true)) throw ValidationException::withMessages(['billing_interval' => ['Choose monthly or annual billing.']]);
        $plan->loadMissing('entitlements');
        $this->assertPlanCapacity($workspace, $plan);
        $subscription = $this->ensureDefault($workspace)->load('plan');
        if ((int) $subscription->subscription_plan_id === (int) $plan->id
            && $subscription->billing_interval === $interval
            && in_array($subscription->status, ['active', 'trialing'], true)
            && ! $subscription->cancel_at_period_end
            && empty(($subscription->provider_metadata ?? [])['grandfathered'])) {
            return $subscription->load('plan.entitlements');
        }
        $metadata = $subscription->provider_metadata ?? [];
        $wasGrandfathered = ! empty($metadata['grandfathered']);
        if ($wasGrandfathered) {
            $metadata['grandfathered'] = false;
            $metadata['billing_activated_at'] = now()->toIso8601String();
        }
        $canTrial = $useTrial
            && ! $wasGrandfathered
            && $subscription->plan->slug === 'free'
            && $plan->trial_days > 0
            && empty($metadata['trial_used_at'])
            && $plan->slug !== 'free';
        $periodStart = now();
        $periodEnd = $plan->slug === 'free' ? null : ($interval === 'annual' ? now()->addYear() : now()->addMonth());

        DB::transaction(function () use ($workspace, $subscription, $plan, $interval, $canTrial, $metadata, $periodStart, &$periodEnd) {
            $seats = max(1, $workspace->members()->where('status', 'active')->count());
            $subscription->invoices()->where('provider', 'manual')->where('status', 'open')->get()->each(function ($invoice) {
                $invoice->update(['status' => 'void', 'amount_due' => 0, 'voided_at' => now(), 'metadata' => array_merge($invoice->metadata ?? [], ['void_reason' => 'plan_change'])]);
            });
            if ($canTrial) {
                $trialEnd = now()->addDays($plan->trial_days);
                $periodEnd = $trialEnd;
                $metadata['trial_used_at'] = now()->toIso8601String();
                $subscription->update([
                    'subscription_plan_id' => $plan->id, 'status' => 'trialing', 'billing_interval' => $interval,
                    'seat_quantity' => $seats, 'trial_started_at' => now(), 'trial_ends_at' => $trialEnd,
                    'current_period_start' => $periodStart, 'current_period_end' => $trialEnd,
                    'cancel_at_period_end' => false, 'canceled_at' => null, 'ended_at' => null, 'grace_ends_at' => null,
                    'provider_metadata' => $metadata,
                ]);
            } else {
                $subscription->update([
                    'subscription_plan_id' => $plan->id, 'status' => 'active', 'billing_interval' => $interval,
                    'seat_quantity' => $seats, 'trial_started_at' => null, 'trial_ends_at' => null,
                    'current_period_start' => $periodStart, 'current_period_end' => $periodEnd,
                    'cancel_at_period_end' => false, 'canceled_at' => null, 'ended_at' => null, 'grace_ends_at' => null,
                    'provider_metadata' => $metadata,
                ]);
                if ($plan->slug !== 'free') $this->createInvoice($subscription->fresh('plan'));
            }
        });

        $this->applyPlanPolicies($workspace, $plan);
        return $subscription->fresh('plan.entitlements');
    }

    /** Creates create invoice data for the requested workflow. */ public function createInvoice(WorkspaceSubscription $subscription): BillingInvoice
    {
        $subscription->loadMissing('plan','workspace');
        $price = $subscription->billing_interval === 'annual' ? (float) $subscription->plan->annual_price_per_seat : (float) $subscription->plan->monthly_price_per_seat;
        $quantity = max(1, $subscription->seat_quantity);
        $subtotal = round($price * $quantity, 2);
        $invoice = BillingInvoice::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $subscription->workspace_id, 'workspace_subscription_id' => $subscription->id,
            'number' => $this->nextInvoiceNumber(), 'status' => $subtotal > 0 ? 'open' : 'paid', 'currency' => $subscription->plan->currency,
            'subtotal' => $subtotal, 'tax_total' => 0, 'discount_total' => 0, 'total' => $subtotal,
            'amount_paid' => $subtotal > 0 ? 0 : $subtotal, 'amount_due' => $subtotal, 'issued_at' => now(), 'due_at' => now()->addDays(14),
            'paid_at' => $subtotal > 0 ? null : now(), 'provider' => $subscription->provider,
        ]);
        $invoice->lines()->create([
            'description' => $subscription->plan->name.' — '.ucfirst($subscription->billing_interval).' subscription',
            'quantity' => $quantity, 'unit_amount' => $price, 'amount' => $subtotal,
            'metadata' => ['plan' => $subscription->plan->slug, 'interval' => $subscription->billing_interval],
        ]);
        return $invoice;
    }

    /** Handles the mark invoice paid operation for the current WorkIntel workflow. */ public function markInvoicePaid(BillingInvoice $invoice, ?string $reference = null): BillingInvoice
    {
        if ($invoice->status === 'paid') return $invoice;
        if (! in_array($invoice->status, ['open','uncollectible'], true)) throw ValidationException::withMessages(['invoice' => ['This invoice cannot be marked paid.']]);
        DB::transaction(function () use ($invoice, $reference) {
            $invoice->update(['status' => 'paid','amount_paid' => $invoice->total,'amount_due' => 0,'paid_at' => now()]);
            BillingTransaction::create([
                'uuid' => (string) Str::uuid(), 'workspace_id' => $invoice->workspace_id, 'billing_invoice_id' => $invoice->id,
                'provider' => $invoice->provider, 'type' => 'payment', 'status' => 'succeeded', 'currency' => $invoice->currency,
                'amount' => $invoice->total, 'provider_transaction_id' => $reference, 'processed_at' => now(),
            ]);
            $invoice->subscription?->update(['status' => 'active','grace_ends_at' => null]);
        });
        return $invoice->fresh('transactions');
    }

    /** Determines whether the cancel at period end condition is satisfied. */ public function cancelAtPeriodEnd(WorkspaceSubscription $subscription): WorkspaceSubscription
    {
        if ($subscription->plan?->slug === 'free') return $subscription;
        $subscription->update(['cancel_at_period_end' => true, 'canceled_at' => now()]);
        return $subscription->fresh('plan.entitlements');
    }

    /** Handles the resume operation for the current WorkIntel workflow. */ public function resume(WorkspaceSubscription $subscription): WorkspaceSubscription
    {
        if ($subscription->ended_at) throw ValidationException::withMessages(['subscription' => ['An ended subscription cannot be resumed. Choose a plan instead.']]);
        $subscription->update(['cancel_at_period_end' => false, 'canceled_at' => null]);
        return $subscription->fresh('plan.entitlements');
    }

    /** Handles the maintenance operation for the current WorkIntel workflow. */ public function maintenance(): array
    {
        $renewed = 0; $downgraded = 0; $pastDue = 0; $expired = 0;
        WorkspaceSubscription::query()->with('plan')->whereIn('status',['active','trialing','past_due'])->chunkById(100, function ($subscriptions) use (&$renewed,&$downgraded,&$pastDue,&$expired) {
            foreach ($subscriptions as $subscription) {
                if ($subscription->status === 'past_due' && $subscription->grace_ends_at?->isPast()) {
                    $subscription->update(['status' => 'expired', 'ended_at' => now()]);
                    $expired++;
                    continue;
                }
                if ($subscription->status === 'trialing' && $subscription->trial_ends_at?->isPast()) {
                    if ($subscription->cancel_at_period_end) { $this->moveToFree($subscription); $downgraded++; continue; }
                    $subscription->update(['status'=>'active','trial_started_at'=>null,'trial_ends_at'=>null,'current_period_start'=>now(),'current_period_end'=>$subscription->billing_interval==='annual'?now()->addYear():now()->addMonth()]);
                    $this->createInvoice($subscription->fresh('plan')); $renewed++; continue;
                }
                if ($subscription->status === 'active' && $subscription->current_period_end?->isPast()) {
                    if ($subscription->cancel_at_period_end) { $this->moveToFree($subscription); $downgraded++; continue; }
                    $subscription->update(['seat_quantity'=>max(1,$subscription->workspace->members()->where('status','active')->count()),'current_period_start'=>now(),'current_period_end'=>$subscription->billing_interval==='annual'?now()->addYear():now()->addMonth()]);
                    if ($subscription->plan->slug !== 'free') $this->createInvoice($subscription); $renewed++;
                }
            }
        });
        BillingInvoice::query()->where('status','open')->where('due_at','<',now())->chunkById(100, function ($invoices) use (&$pastDue) {
            foreach ($invoices as $invoice) { $invoice->update(['status'=>'uncollectible']); $invoice->subscription?->update(['status'=>'past_due','grace_ends_at'=>now()->addDays((int) config('workintel.billing.grace_days',7))]); $pastDue++; }
        });
        return compact('renewed','downgraded','pastDue','expired');
    }

    /** Handles the move to free operation for the current WorkIntel workflow. */ private function moveToFree(WorkspaceSubscription $subscription): void
    {
        $free = SubscriptionPlan::where('slug', 'free')->firstOrFail();
        $metadata = $subscription->provider_metadata ?? [];
        $metadata['previous_plan_ended_at'] = now()->toIso8601String();
        $metadata['previous_plan_slug'] = $subscription->plan?->slug;

        $subscription->update([
            'subscription_plan_id' => $free->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'ended_at' => null,
            'grace_ends_at' => null,
            'provider_metadata' => $metadata,
        ]);
        $this->applyPlanPolicies($subscription->workspace, $free->loadMissing('entitlements'));
    }

    /** Handles the apply plan policies operation for the current WorkIntel workflow. */ private function applyPlanPolicies(Workspace $workspace, SubscriptionPlan $plan): void
    {
        $retention = $plan->entitlements->firstWhere('key', 'limit.screenshot_retention_days')?->resolvedValue();
        if (is_numeric($retention) && (int) $retention > 0) {
            \App\Models\ScreenshotSetting::query()->where('workspace_id', $workspace->id)->where('retention_days', '>', (int) $retention)->update(['retention_days' => (int) $retention]);
        }
    }

    /** Handles the assert plan capacity operation for the current WorkIntel workflow. */ private function assertPlanCapacity(Workspace $workspace, SubscriptionPlan $plan): void
    {
        $limits = $plan->entitlements->filter(fn ($item) => str_starts_with($item->key, 'limit.'))->mapWithKeys(fn ($item) => [$item->key => $item->resolvedValue()]);
        $usage = [
            'members' => $workspace->members()->where('status', 'active')->count(),
            'projects' => $workspace->projects()->where('status', '!=', 'archived')->count(),
            'clients' => $workspace->clients()->where('status', '!=', 'archived')->count(),
            'devices' => $workspace->devices()->where('status', 'active')->count(),
            'saved_reports' => \App\Models\SavedReport::query()->where('workspace_id', $workspace->id)->count(),
            'scheduled_reports' => \App\Models\ReportSchedule::query()->where('workspace_id', $workspace->id)->where('active', true)->count(),
        ];
        $blocked = [];
        foreach ($usage as $resource => $used) {
            $limit = (int) ($limits['limit.'.$resource] ?? -1);
            if ($limit >= 0 && $used > $limit) $blocked[] = sprintf('%s: %d used / %d allowed', str_replace('_', ' ', $resource), $used, $limit);
        }
        if ($blocked) {
            throw ValidationException::withMessages(['plan' => ['This workspace exceeds the target plan limits: '.implode('; ', $blocked).'. Reduce usage before downgrading.']]);
        }
    }

    /** Handles the next invoice number operation for the current WorkIntel workflow. */ private function nextInvoiceNumber(): string
    {
        return 'WI-'.now()->format('Ym').'-'.str_pad((string) (BillingInvoice::query()->whereYear('created_at', now()->year)->count() + 1), 5, '0', STR_PAD_LEFT);
    }
}
