<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\PlanEntitlement;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\PlanCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Exercises Commerce V2 seller plan controls and workspace-owned client billing flows. */
class CommerceV2FlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seeds the complete WorkIntel demo catalog before each commerce flow. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Verifies seller capability edits survive ordinary seed/catalog synchronization. */
    public function test_seller_can_change_plan_capability_without_seed_resetting_it(): void
    {
        [$owner] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $gold = SubscriptionPlan::where('slug', 'gold')->firstOrFail();

        $this->putJson('/api/v1/seller/plans/'.$gold->id.'/entitlements', [
            'entitlements' => ['feature.client_payments' => false],
        ])->assertOk();

        PlanCatalog::sync();
        $value = PlanEntitlement::where('subscription_plan_id', $gold->id)->where('key', 'feature.client_payments')->firstOrFail()->value;
        $this->assertFalse((bool) ($value['value'] ?? true));
    }

    /** Verifies workspace bank payments can be offered in the portal and settled exactly once. */
    public function test_workspace_can_enable_client_pay_now_and_settle_bank_checkout(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);
        $client = Client::where('workspace_id', $member->workspace_id)->where('name', 'TechCorp Inc.')->firstOrFail();

        $gateway = $this->putJson('/api/v1/client-commerce/gateways/bank_transfer', [
            'display_name' => 'Bank Transfer', 'enabled' => true, 'is_default' => true,
            'test_mode' => true, 'client_portal_enabled' => true, 'credentials' => null,
            'settings' => ['instructions' => 'Use invoice number as payment reference.', 'bank_details' => 'WorkIntel Test Bank'],
        ], $headers)->assertOk()->json('data');

        $invoice = $this->postJson('/api/v1/client-invoices', [
            'client_id' => $client->id, 'issue_date' => '2026-08-14', 'due_date' => '2026-08-28',
            'allowed_gateways' => ['bank_transfer'], 'lines' => [['description' => 'Commerce V2 services', 'quantity' => 1, 'unit_price' => 150]],
        ], $headers)->assertCreated()->json('data');
        $this->postJson('/api/v1/client-invoices/'.$invoice['id'].'/send', [], $headers)->assertOk();

        $login = $this->postJson('/api/v1/client-portal/login', ['workspace_slug' => 'acme-corp', 'email' => 'client@techcorp.test', 'password' => 'password'])->assertOk();
        $portalHeaders = ['Authorization' => 'Bearer '.$login->json('token')];
        $this->getJson('/api/v1/client-portal/invoices/'.$invoice['id'].'/payment-options', $portalHeaders)
            ->assertOk()->assertJsonPath('gateways.0.id', $gateway['id']);

        $checkout = $this->postJson('/api/v1/client-portal/invoices/'.$invoice['id'].'/checkout', ['gateway_id' => $gateway['id']], $portalHeaders)
            ->assertCreated()->assertJsonPath('data.provider', 'bank_transfer')->json('data');
        $this->assertNull($checkout['checkout_url']);

        $this->postJson('/api/v1/client-commerce/checkouts/'.$checkout['id'].'/settle', ['reference' => 'BANK-COMMERCE-V2-001'], $headers)
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame(1, ClientPayment::where('client_invoice_id', $invoice['id'])->where('provider_transaction_id', 'BANK-COMMERCE-V2-001')->count());
        $this->assertSame('paid', ClientInvoice::findOrFail($invoice['id'])->status);
    }

    /** Verifies a remote workspace gateway saves credentials first, then enables only after a successful connection test. */
    public function test_workspace_remote_gateway_activation_is_save_test_then_enable(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);

        Http::fake(['https://api.stripe.com/v1/account' => Http::response(['id' => 'acct_workintel_test'], 200)]);
        $this->putJson('/api/v1/client-commerce/gateways/stripe', [
            'display_name' => 'Stripe', 'enabled' => true, 'is_default' => true,
            'test_mode' => true, 'client_portal_enabled' => true,
            'credentials' => ['secret_key' => 'sk_test_workintel'], 'settings' => [],
        ], $headers)->assertOk()
            ->assertJsonPath('activation_test.ok', true)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.health_status', 'healthy')
            ->assertJsonMissingPath('data.credentials');

        Http::fake(['https://api.stripe.com/v1/account' => Http::response(['error' => 'invalid'], 401)]);
        $this->putJson('/api/v1/client-commerce/gateways/stripe', [
            'display_name' => 'Stripe', 'enabled' => true, 'is_default' => true,
            'test_mode' => true, 'client_portal_enabled' => true,
            'credentials' => ['secret_key' => 'sk_test_invalid'], 'settings' => [],
        ], $headers)->assertOk()
            ->assertJsonPath('activation_test.ok', false)
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.is_default', false)
            ->assertJsonPath('data.health_status', 'failed')
            ->assertJsonPath('data.has_credentials', true);
    }

    /** Verifies platform seller provider activation follows the same safe save, test and enable lifecycle. */
    public function test_seller_remote_provider_activation_is_test_gated_without_losing_credentials(): void
    {
        [$owner] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);

        Http::fake(['https://api.stripe.com/v1/account' => Http::response(['id' => 'acct_platform_test'], 200)]);
        $this->putJson('/api/v1/seller/providers/stripe', [
            'display_name' => 'Stripe Platform', 'enabled' => true, 'is_default' => true,
            'test_mode' => true, 'credentials' => ['secret_key' => 'sk_test_platform'], 'settings' => [],
        ])->assertOk()
            ->assertJsonPath('activation_test.ok', true)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.health_status', 'healthy')
            ->assertJsonMissingPath('data.credentials');
    }

    /** Verifies recurring invoice schedules generate through the existing invoice engine. */
    public function test_workspace_can_generate_recurring_client_invoice(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);
        $client = Client::where('workspace_id', $member->workspace_id)->where('name', 'TechCorp Inc.')->firstOrFail();

        $schedule = $this->postJson('/api/v1/client-commerce/invoice-schedules', [
            'client_id' => $client->id, 'name' => 'Monthly Commerce V2 Retainer', 'status' => 'active',
            'frequency' => 'monthly', 'interval_count' => 1, 'due_days' => 14, 'currency' => 'USD',
            'discount_total' => 0, 'tax_percent' => 5, 'auto_send' => true, 'include_unbilled_time' => false,
            'lines' => [['description' => 'Monthly platform services', 'quantity' => 1, 'unit_price' => 250]],
            'allowed_gateways' => [], 'starts_at' => now()->addMonth()->toIso8601String(), 'next_run_at' => now()->addMonth()->toIso8601String(),
        ], $headers)->assertCreated()->json('data');

        $this->postJson('/api/v1/client-commerce/invoice-schedules/'.$schedule['id'].'/run', [], $headers)->assertOk()->assertJsonPath('generated', true);
        $generated = ClientInvoice::where('invoice_schedule_id', $schedule['id'])->firstOrFail();
        $this->assertSame('sent', $generated->status);
        $this->assertSame(250.0, (float) $generated->subtotal);
    }

    /** Verifies ordinary workspace members cannot cross the global platform-operator boundary. */
    public function test_non_operator_cannot_use_seller_platform_api(): void
    {
        [$employee] = $this->userAndMember('employee@acme.test');
        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/seller')->assertForbidden();
    }

    /** Returns a seeded user and active workspace membership. */
    private function userAndMember(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();
        $member = $user->memberships()->with('workspace')->where('status', 'active')->firstOrFail();
        return [$user, $member];
    }

    /** Builds workspace context headers for authenticated API tests. */
    private function headers(int $workspaceId): array
    {
        return ['X-Workspace-Id' => (string) $workspaceId];
    }
}
