<?php

namespace Tests\Feature;

use App\Models\WebsiteFormSubmission;
use App\Models\SubscriptionPlan;
use App\Models\WorkspaceSubscription;
use App\Models\PlanEntitlement;
use App\Models\WorkspaceDomain;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Exercises Website Studio versioning, publishing, custom-domain resolution and encrypted lead capture. */
class WebsitePortalBuilderFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seeds the complete WorkIntel workspace before each Website Studio flow. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Verifies an owner can create, version and publish a page without exposing unsafe rich text. */
    public function test_owner_can_create_version_and_publish_public_page(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);
        $overview = $this->getJson('/api/v1/website/overview', $headers)->assertOk();
        $this->assertNotEmpty($overview->json('pages'));

        $page = $this->postJson('/api/v1/website/pages', [
            'page_type' => 'services', 'title' => 'Services', 'slug' => 'services', 'language' => 'en',
        ], $headers)->assertCreated()->json('data');

        $this->putJson('/api/v1/website/pages/'.$page['id'], [
            'title' => 'Services', 'slug' => 'services', 'language' => 'en',
            'schema' => ['schema_version' => 1, 'sections' => [[
                'id' => 'section_services', 'type' => 'rich_text',
                'settings' => ['html' => '<p>Professional services</p><script>alert(1)</script>'],
            ]]],
        ], $headers)->assertOk()->assertJsonPath('data.current_version', 2);
        $this->postJson('/api/v1/website/pages/'.$page['id'].'/publish', [], $headers)->assertOk()->assertJsonPath('data.status', 'published');

        $public = $this->getJson('/api/v1/public-websites/acme-corp?path=services')->assertOk();
        $html = (string) $public->json('page.schema.sections.0.settings.html');
        $this->assertStringContainsString('Professional services', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    /** Verifies public lead data is encrypted at rest and custom domains resolve only after website assignment. */
    public function test_public_form_capture_is_encrypted_and_custom_domain_resolves(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);
        $overview = $this->getJson('/api/v1/website/overview', $headers)->assertOk();
        $home = collect($overview->json('pages'))->firstWhere('is_home', true);
        $this->assertNotNull($home, 'Website Studio must provide a home page before public-domain delivery is tested.');
        $this->postJson('/api/v1/website/pages/'.$home['id'].'/publish', [], $headers)->assertOk();

        $form = $this->postJson('/api/v1/website/forms', [
            'name' => 'Contact', 'slug' => 'contact', 'status' => 'active',
            'fields' => [
                ['id' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                ['id' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ],
            'settings' => ['require_consent' => true],
            'success_message' => 'Thanks for contacting us.',
        ], $headers)->assertCreated()->json('data');

        $this->putJson('/api/v1/website/site', ['status' => 'published'], $headers)->assertOk();
        $this->postJson('/api/v1/public-websites/acme-corp/forms/'.$form['uuid'].'/submit', [
            'fields' => ['name' => 'Ada Lovelace', 'email' => 'ada@example.test'], 'consent' => true,
        ])->assertCreated();
        $submission = WebsiteFormSubmission::firstOrFail();
        $this->assertSame('ada@example.test', $submission->payload['email']);
        $rawPayload = (string) DB::table('website_form_submissions')->where('id', $submission->id)->value('payload');
        $this->assertStringNotContainsString('ada@example.test', $rawPayload);

        $planId = $member->workspace->subscription()->value('subscription_plan_id');
        PlanEntitlement::query()->where('subscription_plan_id', $planId)->where('key', 'feature.custom_domains')->update(['value' => ['value' => true]]);

        app(SubscriptionService::class)->changePlan($member->workspace, 'platinum', 'monthly', false);
        $platinum = SubscriptionPlan::query()->where('slug', 'platinum')->firstOrFail();
        WorkspaceSubscription::query()->where('workspace_id', $member->workspace_id)->update(['subscription_plan_id' => $platinum->id]);

        $domain = WorkspaceDomain::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'workspace_id' => $member->workspace_id,
            'purpose' => 'website', 'hostname' => 'site.acme.example', 'status' => 'verified',
            'verification_nonce' => 'block-h-test', 'certificate_status' => 'pending',
        ]);
        $this->putJson('/api/v1/website/site', ['custom_domain_id' => $domain->id, 'status' => 'published'], $headers)->assertOk();
        $this->getJson('/api/v1/public-websites/resolve?host=site.acme.example')->assertOk()->assertJsonPath('site.name', 'Acme Corp');
    }

    /** Returns one seeded user and active workspace member. */
    private function userAndMember(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();
        $member = $user->memberships()->with('workspace')->where('status', 'active')->firstOrFail();
        return [$user, $member];
    }

    /** Builds the workspace-selection header used by authenticated APIs. */
    private function headers(int $workspaceId): array
    {
        return ['X-Workspace-Id' => (string) $workspaceId];
    }
}
