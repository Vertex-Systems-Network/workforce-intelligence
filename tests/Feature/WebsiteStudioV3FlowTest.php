<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsitePageDraft;
use App\Models\WebsitePreviewToken;
use App\Models\WebsiteReusableSection;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Certifies Website Studio V3 mutable autosave, immutable save and server preflight behavior. */
class WebsiteStudioV3FlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seeds the complete WorkIntel workspace before Website Studio V3 flows. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Ensures autosave survives reload without incrementing immutable page history and clears after manual save. */
    public function test_autosave_is_mutable_until_explicit_version_save(): void
    {
        [$headers, $page] = $this->ownerPage();
        $before = (int) $page['current_version'];
        $schema = ['schema_version' => 1, 'sections' => [['id' => 'section_autosave', 'type' => 'hero', 'settings' => ['title' => 'Autosaved hero', 'primary_url' => '#']]]];
        $this->putJson('/api/v1/website/pages/'.$page['id'].'/draft', ['schema' => $schema, 'metadata' => ['title' => 'Autosaved title']], $headers)->assertOk()->assertJsonPath('data.revision', 1);
        $this->assertSame($before, (int) $this->getJson('/api/v1/website/pages/'.$page['id'], $headers)->assertOk()->json('page.current_version'));
        $this->getJson('/api/v1/website/pages/'.$page['id'], $headers)->assertOk()->assertJsonPath('draft.metadata.title', 'Autosaved title');
        $this->putJson('/api/v1/website/pages/'.$page['id'], ['title' => 'Autosaved title', 'slug' => $page['slug'], 'language' => $page['language'], 'schema' => $schema], $headers)->assertOk()->assertJsonPath('data.current_version', $before + 1);
        $this->assertFalse(WebsitePageDraft::query()->where('website_page_id', $page['id'])->exists());
    }

    /** Ensures server preflight blocks fatal structure/link problems while allowing warning-only pages. */
    public function test_server_preflight_returns_blocking_errors_and_warning_only_ready_state(): void
    {
        [$headers, $page] = $this->ownerPage();
        $this->postJson('/api/v1/website/pages/'.$page['id'].'/preflight', ['schema' => ['schema_version' => 1, 'sections' => []]], $headers)->assertOk()->assertJsonPath('data.ready', false)->assertJsonPath('data.summary.errors', 1);
        $valid = ['schema_version' => 1, 'sections' => [['id' => 'section_ok', 'type' => 'hero', 'settings' => ['title' => 'A valid page hero', 'primary_url' => '#']]]];
        $this->postJson('/api/v1/website/pages/'.$page['id'].'/preflight', ['schema' => $valid, 'metadata' => ['title' => 'A valid page']], $headers)->assertOk()->assertJsonPath('data.ready', true);
        $unsafe = ['schema_version' => 1, 'sections' => [['id' => 'section_bad', 'type' => 'cta', 'settings' => ['title' => 'Unsafe', 'button_url' => 'javascript:alert(1)']]]];
        $this->postJson('/api/v1/website/pages/'.$page['id'].'/preflight', ['schema' => $unsafe], $headers)->assertOk()->assertJsonPath('data.ready', false);
    }


    /** Ensures staging previews are immutable, non-cacheable and revocable even when later autosaves exist. */
    public function test_staging_preview_is_immutable_private_and_revocable(): void
    {
        [$headers, $page] = $this->ownerPage();
        $schema = ['schema_version' => 1, 'sections' => [['id' => 'section_stage', 'type' => 'hero', 'settings' => ['title' => 'Reviewer snapshot', 'primary_url' => '#']]]];
        $stage = $this->postJson('/api/v1/website/pages/'.$page['id'].'/stage', ['schema' => $schema, 'metadata' => ['title' => 'Reviewer snapshot']], $headers)->assertOk();
        $version = (int) $stage->json('data.staged_version');
        $this->assertGreaterThan(0, $version);

        $share = $this->postJson('/api/v1/website/pages/'.$page['id'].'/preview-tokens', ['expires_hours' => 24], $headers)
            ->assertCreated()->assertJsonPath('data.version', $version);
        $url = (string) $share->json('data.url');
        $token = basename($url);

        $preview = $this->getJson('/api/v1/public-websites/preview/'.$token)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertJsonPath('is_preview', true)
            ->assertJsonPath('page.schema.sections.0.settings.title', 'Reviewer snapshot');
        $this->assertStringContainsString('no-store', (string) $preview->headers->get('Cache-Control'));

        $later = ['schema_version' => 1, 'sections' => [['id' => 'section_stage', 'type' => 'hero', 'settings' => ['title' => 'Later autosave', 'primary_url' => '#']]]];
        $this->putJson('/api/v1/website/pages/'.$page['id'].'/draft', ['schema' => $later, 'metadata' => ['title' => 'Later autosave']], $headers)->assertOk();
        $this->getJson('/api/v1/public-websites/preview/'.$token)->assertOk()->assertJsonPath('page.schema.sections.0.settings.title', 'Reviewer snapshot');

        $previewId = (int) $this->getJson('/api/v1/website/pages/'.$page['id'], $headers)->assertOk()->json('preview_tokens.0.id');
        $this->deleteJson('/api/v1/website/preview-tokens/'.$previewId, [], $headers)->assertOk();
        $this->getJson('/api/v1/public-websites/preview/'.$token)->assertNotFound();
        $this->assertNotNull(WebsitePreviewToken::query()->findOrFail($previewId)->revoked_at);
    }

    /** Ensures review comments can be section-scoped and resolved without deleting review history. */
    public function test_review_comments_are_section_scoped_and_resolvable(): void
    {
        [$headers, $page] = $this->ownerPage();
        $schema = ['schema_version' => 1, 'sections' => [['id' => 'section_review', 'type' => 'hero', 'settings' => ['title' => 'Review me', 'primary_url' => '#']]]];
        $this->putJson('/api/v1/website/pages/'.$page['id'].'/draft', ['schema' => $schema], $headers)->assertOk();
        $comment = $this->postJson('/api/v1/website/pages/'.$page['id'].'/comments', ['section_id' => 'section_review', 'message' => 'Tighten this headline.'], $headers)
            ->assertCreated()->assertJsonPath('data.status', 'open')->assertJsonPath('data.section_id', 'section_review');
        $commentId = (int) $comment->json('data.id');
        $this->patchJson('/api/v1/website/comments/'.$commentId, ['status' => 'resolved'], $headers)->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->getJson('/api/v1/website/pages/'.$page['id'].'/comments', $headers)->assertOk()->assertJsonFragment(['message' => 'Tighten this headline.', 'status' => 'resolved']);
    }

    /** Ensures global reusable component edits propagate only into mutable linked drafts. */
    public function test_global_reusable_component_propagates_into_linked_draft(): void
    {
        [$headers, $page] = $this->ownerPage();
        $source = ['id' => 'source_hero', 'type' => 'hero', 'settings' => ['title' => 'Global source A', 'primary_url' => '#']];
        $component = $this->postJson('/api/v1/website/reusable-sections', ['name' => 'Global Hero', 'schema' => $source, 'is_global' => true], $headers)
            ->assertCreated()->json('data');
        $this->assertTrue(WebsiteReusableSection::query()->findOrFail((int) $component['id'])->is_global);

        $linked = ['schema_version' => 1, 'sections' => [['id' => 'linked_instance', 'type' => 'hero', 'settings' => ['title' => 'Linked old content', 'primary_url' => '#', 'linked_reusable_uuid' => $component['uuid']]]]];
        $this->putJson('/api/v1/website/pages/'.$page['id'].'/draft', ['schema' => $linked], $headers)->assertOk();

        $updatedSource = ['id' => 'source_hero', 'type' => 'hero', 'settings' => ['title' => 'Global source B', 'primary_url' => '#']];
        $this->putJson('/api/v1/website/reusable-sections/'.$component['id'], ['name' => 'Global Hero', 'schema' => $updatedSource, 'is_global' => true], $headers)->assertOk();
        $pagePayload = $this->getJson('/api/v1/website/pages/'.$page['id'], $headers)->assertOk();
        $pagePayload->assertJsonPath('draft.schema.sections.0.settings.title', 'Global source B');
        $pagePayload->assertJsonPath('draft.schema.sections.0.settings.linked_reusable_uuid', $component['uuid']);
    }

    /** Ensures archived pages cannot be silently staged for review. */
    public function test_archived_page_must_be_restored_before_staging(): void
    {
        [$headers] = $this->ownerPage();
        $page = $this->postJson('/api/v1/website/pages', ['page_type' => 'standard', 'title' => 'Archive staging guard', 'slug' => 'archive-staging-guard', 'language' => 'en', 'is_home' => false], $headers)
            ->assertCreated()->json('data');
        $this->deleteJson('/api/v1/website/pages/'.$page['id'], [], $headers)->assertOk();
        $schema = ['schema_version' => 1, 'sections' => [['id' => 'archived_stage', 'type' => 'hero', 'settings' => ['title' => 'Archived', 'primary_url' => '#']]]];
        $this->postJson('/api/v1/website/pages/'.$page['id'].'/stage', ['schema' => $schema], $headers)->assertStatus(422);
    }

    /** Returns owner workspace headers and one existing website page. */
    private function ownerPage(): array
    {
        $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
        $member = $owner->memberships()->where('status', 'active')->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $member->workspace_id];
        $page = $this->getJson('/api/v1/website/overview', $headers)->assertOk()->json('pages.0');
        return [$headers, $page];
    }
}
