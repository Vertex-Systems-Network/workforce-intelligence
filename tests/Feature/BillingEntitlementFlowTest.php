<?php

namespace Tests\Feature;

use App\Models\BillingInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides billing entitlement flow test behavior within the WorkIntel application. */ class BillingEntitlementFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test demo workspace has gold plan and owner can manage billing operation for the current WorkIntel workflow. */ public function test_demo_workspace_has_gold_plan_and_owner_can_manage_billing(): void
    {
        config(['workintel.billing.allow_manual_settlement' => true]);
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $this->getJson('/api/v1/billing', $headers)
            ->assertOk()
            ->assertJsonPath('subscription.plan.slug', 'gold')
            ->assertJsonPath('entitlements.feature.payroll', true)
            ->assertJsonCount(4, 'plans');

        $this->postJson('/api/v1/billing/subscription/change', [
            'plan_slug' => 'platinum',
            'billing_interval' => 'annual',
            'use_trial' => false,
        ], $headers)->assertOk()->assertJsonPath('subscription.plan.slug', 'platinum');

        $invoice = BillingInvoice::where('workspace_id', $membership->workspace_id)->where('status', 'open')->latest()->firstOrFail();
        $this->postJson('/api/v1/billing/invoices/'.$invoice->id.'/mark-paid', ['reference' => 'TEST-PAYMENT'], $headers)
            ->assertOk()->assertJsonPath('invoice.status', 'paid');

        $this->postJson('/api/v1/billing/subscription/cancel', [], $headers)
            ->assertOk()->assertJsonPath('subscription.cancel_at_period_end', true);
        $this->postJson('/api/v1/billing/subscription/resume', [], $headers)
            ->assertOk()->assertJsonPath('subscription.cancel_at_period_end', false);
    }

    /** Handles the test downgrade is blocked when current usage exceeds target limits operation for the current WorkIntel workflow. */ public function test_downgrade_is_blocked_when_current_usage_exceeds_target_limits(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/billing/subscription/change', [
            'plan_slug' => 'free', 'billing_interval' => 'monthly', 'use_trial' => false,
        ], ['X-Workspace-Id' => (string) $membership->workspace_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');
    }

    /** Handles the test new workspace starts free and plan feature middleware blocks advanced reports operation for the current WorkIntel workflow. */ public function test_new_workspace_starts_free_and_plan_feature_middleware_blocks_advanced_reports(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'New', 'last_name' => 'Owner', 'email' => 'new-billing-owner@example.test',
            'company_name' => 'Free Workspace', 'password' => 'SecureBilling#123', 'timezone' => 'UTC',
        ])->assertCreated();

        $workspace = Workspace::where('slug', 'free-workspace')->firstOrFail();
        $subscription = WorkspaceSubscription::where('workspace_id', $workspace->id)->with('plan')->firstOrFail();
        $this->assertSame('free', $subscription->plan->slug);

        $this->getJson('/api/v1/reports/catalog', ['X-Workspace-Id' => (string) $workspace->id])
            ->assertStatus(402)
            ->assertJsonPath('code', 'PLAN_FEATURE_REQUIRED');

        $this->postJson('/api/v1/billing/subscription/change', [
            'plan_slug' => 'silver', 'billing_interval' => 'monthly', 'use_trial' => true,
        ], ['X-Workspace-Id' => (string) $workspace->id])
            ->assertOk()
            ->assertJsonPath('subscription.status', 'trialing')
            ->assertJsonPath('subscription.plan.slug', 'silver');
    }

    /** Handles the test plan catalog contains expected four tiers operation for the current WorkIntel workflow. */ public function test_plan_catalog_contains_expected_four_tiers(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
        $this->assertSame(['free','silver','gold','platinum'], SubscriptionPlan::orderBy('sort_order')->pluck('slug')->all());
    }
}
