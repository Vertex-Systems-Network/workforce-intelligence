<?php

namespace Tests\Feature;

use App\Models\ReportRun;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides reporting flow test behavior within the WorkIntel application. */ class ReportingFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test owner can build save run export and schedule reports operation for the current WorkIntel workflow. */ public function test_owner_can_build_save_run_export_and_schedule_reports(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $catalog = $this->getJson('/api/v1/reports/catalog', $headers)->assertOk();
        $this->assertTrue($catalog->json('can_manage'));
        $this->assertContains('time_entries', collect($catalog->json('datasets'))->pluck('key')->all());

        $configuration = [
            'dataset' => 'time_entries', 'date_preset' => 'custom', 'date_from' => '2026-08-01', 'date_to' => '2026-08-31',
            'dimensions' => ['employee'], 'metrics' => ['tracked_hours', 'billable_hours'], 'filters' => [],
            'sort' => ['key' => 'tracked_hours', 'direction' => 'desc'], 'limit' => 5000,
            'visualization' => ['type' => 'bar', 'x' => 'employee', 'y' => 'tracked_hours'],
        ];

        $preview = $this->postJson('/api/v1/reports/preview', ['configuration' => $configuration], $headers)->assertOk();
        $this->assertGreaterThan(0, $preview->json('row_count'));

        $saved = $this->postJson('/api/v1/reports/saved', [
            'name' => 'Test Team Time', 'description' => 'Reporting feature test.', 'is_shared' => true, 'configuration' => $configuration,
        ], $headers)->assertCreated()->json('data');

        $run = $this->postJson('/api/v1/reports/saved/'.$saved['id'].'/run', [], $headers)->assertCreated()->json('data');
        $this->assertSame('completed', $run['status']);
        $this->assertGreaterThan(0, $run['row_count']);

        $export = $this->postJson('/api/v1/reports/runs/'.$run['id'].'/exports', ['format' => 'csv'], $headers)->assertCreated()->json('data');
        $this->assertSame('completed', $export['status']);
        $this->get('/api/v1/reports/exports/'.$export['id'].'/download', $headers)->assertOk();

        $pdf = $this->postJson('/api/v1/reports/runs/'.$run['id'].'/exports', ['format' => 'pdf'], $headers)->assertCreated()->json('data');
        $this->assertSame('completed', $pdf['status']);

        $schedule = $this->postJson('/api/v1/reports/schedules', [
            'saved_report_id' => $saved['id'], 'name' => 'Monday Test Report', 'frequency' => 'weekly', 'time_of_day' => '08:00',
            'day_of_week' => 1, 'day_of_month' => null, 'timezone' => 'UTC', 'export_format' => 'csv', 'active' => true,
        ], $headers)->assertCreated()->json('data');
        $this->assertNotNull($schedule['next_run_at']);

        $before = ReportRun::where('workspace_id', $membership->workspace_id)->count();
        $this->postJson('/api/v1/reports/schedules/'.$schedule['id'].'/run-now', [], $headers)->assertOk();
        $this->assertGreaterThan($before, ReportRun::where('workspace_id', $membership->workspace_id)->count());
    }

    /** Handles the test project money report requires currency dimension when multiple currencies exist operation for the current WorkIntel workflow. */ public function test_project_money_report_requires_currency_dimension_when_multiple_currencies_exist(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        \App\Models\Project::where('workspace_id', $membership->workspace_id)->firstOrFail()->update(['currency' => 'EUR']);
        $this->postJson('/api/v1/reports/preview', ['configuration' => [
            'dataset' => 'projects', 'date_preset' => 'custom', 'date_from' => '2026-07-01', 'date_to' => '2026-08-31',
            'dimensions' => [], 'metrics' => ['project_revenue', 'profit'], 'filters' => [],
        ]], $headers)->assertStatus(422);
    }
}
