<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides client portal invoice flow test behavior within the WorkIntel application. */ class ClientPortalInvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test client portal is scoped and client invoicing workflow is available operation for the current WorkIntel workflow. */ public function test_client_portal_is_scoped_and_client_invoicing_workflow_is_available(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-12 12:00:00'));
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];
        Sanctum::actingAs($owner);

        $techCorp = Client::where('workspace_id', $membership->workspace_id)->where('name', 'TechCorp Inc.')->firstOrFail();
        $dataFlow = Client::where('workspace_id', $membership->workspace_id)->where('name', 'DataFlow Ltd.')->firstOrFail();

        $this->getJson('/api/v1/clients/'.$techCorp->id.'/portal', $headers)
            ->assertOk()
            ->assertJsonPath('accounts.0.email', 'client@techcorp.test');

        $created = $this->postJson('/api/v1/client-invoices', [
            'client_id' => $techCorp->id,
            'issue_date' => '2026-08-11',
            'due_date' => '2026-08-25',
            'tax_percent' => 5,
            'discount_total' => 10,
            'include_unbilled_time' => false,
            'lines' => [[
                'description' => 'Consulting retainer',
                'quantity' => 2,
                'unit_price' => 500,
            ]],
        ], $headers)->assertCreated()->json('data');

        $this->assertSame('draft', $created['status']);
        $invoiceId = $created['id'];

        $this->postJson('/api/v1/client-invoices/'.$invoiceId.'/send', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        $this->postJson('/api/v1/client-invoices/'.$invoiceId.'/payments', [
            'amount' => 250,
            'currency' => 'USD',
            'method' => 'bank_transfer',
            'reference' => 'TEST-PARTIAL',
            'paid_on' => '2026-08-12',
        ], $headers)->assertCreated()->assertJsonPath('invoice.status', 'partial');

        $report = $this->postJson('/api/v1/client-reports', [
            'client_id' => $techCorp->id,
            'name' => 'Client Time Summary',
            'report_type' => 'time_summary',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'publish' => true,
        ], $headers)->assertCreated()->json('data');
        $this->assertNotNull($report['published_at']);

        $login = $this->postJson('/api/v1/client-portal/login', [
            'workspace_slug' => 'acme-corp',
            'email' => 'client@techcorp.test',
            'password' => 'password',
        ])->assertOk();
        $token = $login->json('token');
        $this->assertStringStartsWith('wicp_', $token);
        $portalHeaders = ['Authorization' => 'Bearer '.$token];

        $dashboard = $this->getJson('/api/v1/client-portal/dashboard', $portalHeaders)->assertOk();
        $this->assertSame($techCorp->id, $dashboard->json('client.id'));

        $projects = $this->getJson('/api/v1/client-portal/projects', $portalHeaders)->assertOk()->json('data');
        $this->assertContains('API Platform', array_column($projects, 'name'));
        $this->assertNotContains('Analytics Pipeline', array_column($projects, 'name'));

        $foreignProject = Project::where('client_id', $dataFlow->id)->firstOrFail();
        $this->getJson('/api/v1/client-portal/projects/'.$foreignProject->id, $portalHeaders)->assertNotFound();

        $portalInvoices = $this->getJson('/api/v1/client-portal/invoices', $portalHeaders)->assertOk()->json('data');
        $this->assertContains($created['number'], array_column($portalInvoices, 'number'));

        $pdf = $this->get('/api/v1/client-portal/invoices/'.$invoiceId.'/pdf', $portalHeaders)->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());

        $portalReports = $this->getJson('/api/v1/client-portal/reports', $portalHeaders)->assertOk()->json('data');
        $this->assertContains('Client Time Summary', array_column($portalReports, 'name'));

        $reportPdf = $this->get('/api/v1/client-portal/reports/'.$report['id'].'/pdf', $portalHeaders)->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $reportPdf->getContent());

        $this->postJson('/api/v1/client-invoices', [
            'client_id' => $techCorp->id,
            'currency' => 'EUR',
            'issue_date' => '2026-08-11',
            'lines' => [['description' => 'FX should not be implicit', 'quantity' => 1, 'unit_price' => 10]],
        ], $headers)->assertUnprocessable();
    }

    /** Handles the test client portal invite can only be activated once operation for the current WorkIntel workflow. */ public function test_client_portal_invite_can_only_be_activated_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $client = Client::where('workspace_id', $membership->workspace_id)->where('name', 'DataFlow Ltd.')->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $invite = $this->postJson('/api/v1/clients/'.$client->id.'/portal/invites', [
            'name' => 'Dana Client',
            'email' => 'dana@dataflow.test',
            'expires_hours' => 24,
        ], $headers)->assertCreated()->json('invite');

        $fragment = parse_url($invite['activation_url'], PHP_URL_FRAGMENT);
        parse_str((string) $fragment, $query);
        $token = $query['token'];

        $this->postJson('/api/v1/client-portal/activate', [
            'workspace_slug' => 'acme-corp',
            'token' => $token,
            'password' => 'client-password-123',
        ])->assertCreated()->assertJsonPath('portal.email', 'dana@dataflow.test');

        $this->postJson('/api/v1/client-portal/activate', [
            'workspace_slug' => 'acme-corp',
            'token' => $token,
            'password' => 'client-password-123',
        ])->assertUnprocessable();
    }
}
