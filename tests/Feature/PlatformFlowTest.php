<?php

namespace Tests\Feature;

use App\Models\AddonUsageEvent;
use App\Models\DataImportJob;
use App\Models\EmployeeCustomField;
use App\Models\IndustryTemplate;
use App\Models\IndustryTemplateInstallation;
use App\Models\PartnerApiKey;
use App\Models\PlatformAddon;
use App\Models\User;
use App\Models\WorkspaceAddon;
use App\Services\Billing\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides platform phase26 flow test behavior within the WorkIntel application. */ class PlatformFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test owner can use platform catalog import sandbox and partner api operation for the current WorkIntel workflow. */ public function test_owner_can_use_platform_catalog_import_sandbox_and_partner_api(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $member = $owner->memberships()->with('workspace')->firstOrFail();
        $workspace = $member->workspace;
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $workspace->id];

        $overview = $this->getJson('/api/v1/platform/overview', $headers)->assertOk();
        $this->assertTrue((bool) $overview->json('entitlements.feature.addon_marketplace'));
        $this->assertNotEmpty($overview->json('addons'));
        $this->assertNotEmpty($overview->json('templates'));

        // Gold includes one isolated sandbox.
        $sandbox = $this->postJson('/api/v1/platform/sandboxes', ['name' => 'ACME QA Sandbox', 'days' => 14], $headers)
            ->assertCreated()->json('data');
        $this->assertSame('sandbox', $sandbox['workspace_type']);
        $this->assertDatabaseHas('workspaces', ['id' => $sandbox['id'], 'parent_workspace_id' => $workspace->id, 'workspace_type' => 'sandbox']);
        $this->assertDatabaseHas('workspace_members', ['workspace_id' => $sandbox['id'], 'user_id' => $owner->id]);

        // Import a person through the row-ledger wizard.
        $csv = "email,first_name,last_name,job_title,department\nimported@example.test,Import,Person,Analyst,Operations\n";
        $file = UploadedFile::fake()->createWithContent('people.csv', $csv);
        $mapping = json_encode(['email'=>'email','first_name'=>'first_name','last_name'=>'last_name','job_title'=>'job_title','department'=>'department']);
        $job = $this->post('/api/v1/platform/imports', ['file'=>$file,'entity_type'=>'people','source_system'=>'csv','mapping'=>$mapping], $headers)
            ->assertCreated()->json('data');
        $this->postJson('/api/v1/platform/imports/'.$job['id'].'/run', [], $headers)->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('users', ['email' => 'imported@example.test']);
        $this->assertSame(1, DataImportJob::findOrFail($job['id'])->items()->where('status', 'imported')->count());

        // Implementation templates are repeatable without changing stable UUIDs.
        $template = IndustryTemplate::where('slug', 'software-saas')->firstOrFail();
        $this->postJson('/api/v1/platform/templates/'.$template->id.'/install', [], $headers)->assertCreated();
        $fieldUuid = EmployeeCustomField::where('workspace_id', $workspace->id)->where('key', 'github_username')->value('uuid');
        $this->postJson('/api/v1/platform/templates/'.$template->id.'/install', [], $headers)->assertCreated();
        $this->assertSame($fieldUuid, EmployeeCustomField::where('workspace_id', $workspace->id)->where('key', 'github_username')->value('uuid'));
        $this->assertSame(2, IndustryTemplateInstallation::where('workspace_id', $workspace->id)->where('industry_template_id', $template->id)->count());

        // Marketplace subscription + idempotent metered usage.
        $metered = PlatformAddon::where('slug', 'extended-storage')->firstOrFail();
        $subscription = $this->postJson('/api/v1/platform/addons/'.$metered->id.'/subscribe', ['quantity'=>1], $headers)->assertCreated()->json('data');
        $usage = ['metric'=>'storage_gb_month','quantity'=>5,'idempotency_key'=>'test-usage-001'];
        $this->postJson('/api/v1/platform/addons/'.$subscription['id'].'/usage', $usage, $headers)->assertCreated();
        $this->postJson('/api/v1/platform/addons/'.$subscription['id'].'/usage', $usage, $headers)->assertCreated();
        $this->assertSame(1, AddonUsageEvent::where('workspace_addon_id', $subscription['id'])->where('idempotency_key', 'test-usage-001')->count());

        // Platinum unlocks white-label, custom domains and partner platform/API.
        app(SubscriptionService::class)->changePlan($workspace, 'platinum', 'monthly', false);
        $this->postJson('/api/v1/platform/branding', ['product_name'=>'ACME Workforce','accent_color'=>'#112233','hide_powered_by'=>true], $headers)
            ->assertOk()->assertJsonPath('data.product_name', 'ACME Workforce');
        $this->postJson('/api/v1/platform/domains', ['hostname'=>'localhost'], $headers)->assertUnprocessable();
        $domain = $this->postJson('/api/v1/platform/domains', ['hostname'=>'team.example.com'], $headers)->assertCreated()->json('data');
        $this->assertSame('pending', $domain['status']);
        $this->assertStringStartsWith('workintel-verify=', $domain['verification_record']);

        $partner = $this->postJson('/api/v1/platform/partners', ['name'=>'ACME Agency','type'=>'agency'], $headers)->assertCreated()->json('data');
        $keyResponse = $this->postJson('/api/v1/platform/partners/'.$partner['id'].'/api-keys', ['name'=>'CI Partner','scopes'=>['workspaces.read','addons.read']], $headers)->assertCreated();
        $plain = $keyResponse->json('token');
        $this->assertStringStartsWith('wip_', $plain);
        $key = PartnerApiKey::where('partner_account_id', $partner['id'])->firstOrFail();
        $this->assertNotSame($plain, $key->token_hash);
        $this->assertSame(hash('sha256', $plain), $key->token_hash);
        $this->withToken($plain)->getJson('/api/partner/v1/me')->assertOk()->assertJsonPath('partner.slug', 'acme-agency');
        $this->withToken($plain)->getJson('/api/partner/v1/workspaces')->assertOk();
        $this->withToken($plain)->postJson('/api/partner/v1/workspaces', ['name' => 'Denied Workspace'])->assertForbidden();
    }

    /** Handles the test platform default access is owner admin only operation for the current WorkIntel workflow. */ public function test_platform_default_access_is_owner_admin_only(): void
    {
        $this->seed(DatabaseSeeder::class);
        $employee = User::where('email', 'employee@acme.test')->firstOrFail();
        $membership = $employee->memberships()->firstOrFail();
        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/platform/overview', ['X-Workspace-Id'=>(string)$membership->workspace_id])->assertForbidden();
    }

    /** Handles the test archived sandbox is not a usable workspace context operation for the current WorkIntel workflow. */ public function test_archived_sandbox_is_not_a_usable_workspace_context(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->with('workspace')->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $sandbox = $this->postJson('/api/v1/platform/sandboxes', [
            'name' => 'Archive Guard Sandbox', 'days' => 7,
        ], $headers)->assertCreated()->json('data');

        $this->postJson('/api/v1/platform/sandboxes/'.$sandbox['id'].'/archive', [], $headers)->assertOk();
        $me = $this->getJson('/api/v1/auth/me')->assertOk();
        $this->assertNotContains($sandbox['id'], collect($me->json('user.workspaces'))->pluck('id')->all());
        $this->getJson('/api/v1/platform/overview', ['X-Workspace-Id' => (string) $sandbox['id']])->assertForbidden();
    }
}
