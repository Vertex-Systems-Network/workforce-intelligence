<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\MediaUsage;
use App\Models\WebsiteForm;
use App\Models\WebsiteFormSubmission;
use App\Models\WebsitePage;
use App\Models\WebsitePageVersion;
use App\Models\WebsitePageDraft;
use App\Models\WebsitePageComment;
use App\Models\WebsitePreviewToken;
use App\Models\WebsiteReusableSectionLink;
use App\Models\WebsiteReusableSection;
use App\Models\WebsiteSite;
use App\Models\Workspace;
use App\Models\WorkspaceDomain;
use App\Models\WorkspaceMember;
use App\Services\Media\MediaLibraryService;
use App\Services\Billing\EntitlementService;
use App\Services\Notifications\WorkspaceNotificationService;
use App\Services\Integrations\WebhookService;
use App\Support\WebsiteBuilderCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Owns Website Studio versioning, publishing, reusable sections, public rendering and lead capture. */
class WebsiteBuilderService
{
    /** Injects media usage and workspace notification collaborators. */
    public function __construct(
        private readonly MediaLibraryService $media,
        private readonly WorkspaceNotificationService $notifications,
        private readonly EntitlementService $entitlements,
        private readonly WebhookService $webhooks,
    ) {}

    /** Creates the workspace site and a first Home page when Website Studio is opened for the first time. */
    public function ensureSite(Workspace $workspace, WorkspaceMember $actor): WebsiteSite
    {
        return DB::transaction(function () use ($workspace, $actor) {
            $site = WebsiteSite::firstOrCreate(['workspace_id' => $workspace->id], [
                'uuid' => (string) Str::uuid(),
                'name' => $workspace->name,
                'status' => 'draft',
                'default_language' => 'en',
                'supported_languages' => ['en'],
                'theme' => WebsiteBuilderCatalog::theme(),
                'header_config' => WebsiteBuilderCatalog::header(),
                'footer_config' => WebsiteBuilderCatalog::footer(),
                'seo_defaults' => ['title_suffix' => $workspace->name, 'description' => ''],
                'created_by_member_id' => $actor->id,
                'updated_by_member_id' => $actor->id,
            ]);

            if (! $site->pages()->exists()) {
                $this->createPage($site, $actor, ['page_type' => 'home', 'title' => 'Home', 'slug' => 'home', 'language' => $site->default_language, 'is_home' => true]);
            }

            return $site->fresh(['customDomain']);
        });
    }

    /** Returns the complete authenticated editor payload for one workspace. */
    public function overview(Workspace $workspace, WorkspaceMember $actor): array
    {
        $site = $this->ensureSite($workspace, $actor);
        $pages = WebsitePage::query()->where('workspace_id', $workspace->id)->with('ogMedia:id,uuid,name,mime_type,alt_text')->orderBy('sort_order')->orderBy('title')->get();
        $forms = WebsiteForm::query()->where('workspace_id', $workspace->id)->withCount('submissions')->orderBy('name')->get();
        $sections = WebsiteReusableSection::query()->where('workspace_id', $workspace->id)->orderByDesc('is_global')->orderBy('name')->get();
        $domains = WorkspaceDomain::query()->where('workspace_id', $workspace->id)->whereIn('status', ['verified','active'])->orderBy('hostname')->get(['id','hostname','status','purpose','certificate_status']);
        $submissionSummary = WebsiteFormSubmission::query()->where('workspace_id', $workspace->id)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return [
            'site' => $site,
            'pages' => $pages,
            'forms' => $forms,
            'reusable_sections' => $sections,
            'domains' => $domains,
            'submission_summary' => $submissionSummary,
            'catalog' => ['page_types' => WebsiteBuilderCatalog::PAGE_TYPES, 'section_types' => WebsiteBuilderCatalog::SECTION_TYPES],
            'permissions' => [
                'manage' => $actor->hasPermission('website.manage'),
                'publish' => $actor->hasPermission('website.publish'),
                'forms_manage' => $actor->hasPermission('website.forms_manage'),
                'submissions_view' => $actor->hasPermission('website.submissions_view'),
            ],
        ];
    }

    /** Updates global site theme, languages, header/footer, SEO defaults and custom-domain assignment. */
    public function updateSite(WebsiteSite $site, WorkspaceMember $actor, array $data): WebsiteSite
    {
        return DB::transaction(function () use ($site, $actor, $data) {
            if (array_key_exists('custom_domain_id', $data) && $data['custom_domain_id']) {
                abort_unless($this->entitlements->allows($site->workspace, 'feature.custom_domains'), 402, 'Custom domains are not available on the current plan.');
                $domain = WorkspaceDomain::query()->where('workspace_id', $site->workspace_id)->whereIn('status', ['verified','active'])->findOrFail((int) $data['custom_domain_id']);
                $domain->update(['purpose' => 'website']);
            }
            if ($site->custom_domain_id && array_key_exists('custom_domain_id', $data) && (int) $data['custom_domain_id'] !== (int) $site->custom_domain_id) {
                WorkspaceDomain::query()->whereKey($site->custom_domain_id)->where('purpose', 'website')->update(['purpose' => 'workspace']);
            }
            $payload = collect($data)->only(['name','status','default_language','supported_languages','theme','header_config','footer_config','seo_defaults','custom_domain_id'])->all();
            $payload['updated_by_member_id'] = $actor->id;
            if (($payload['status'] ?? $site->status) === 'published' && ! $site->published_at) $payload['published_at'] = now();
            $site->update($payload);
            return $site->fresh(['customDomain']);
        });
    }

    /** Creates a page with an immutable first revision and normalized unique slug. */
    public function createPage(WebsiteSite $site, WorkspaceMember $actor, array $data): WebsitePage
    {
        $this->entitlements->assertWithinLimit($site->workspace, 'website_pages', $site->pages()->count());
        return DB::transaction(function () use ($site, $actor, $data) {
            $type = in_array($data['page_type'] ?? 'custom', WebsiteBuilderCatalog::PAGE_TYPES, true) ? $data['page_type'] : 'custom';
            $language = strtolower((string) ($data['language'] ?? $site->default_language));
            $slug = $this->uniqueSlug($site, Str::slug((string) ($data['slug'] ?? $data['title'] ?? $type)) ?: $type, $language);
            $isHome = (bool) ($data['is_home'] ?? $type === 'home');
            if ($isHome) WebsitePage::query()->where('website_site_id', $site->id)->where('language', $language)->update(['is_home' => false]);
            $page = WebsitePage::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $site->workspace_id,
                'website_site_id' => $site->id,
                'page_type' => $type,
                'language' => $language,
                'title' => Str::limit(trim((string) ($data['title'] ?? ucfirst($type))), 180, ''),
                'slug' => $isHome ? 'home' : $slug,
                'status' => 'draft',
                'is_home' => $isHome,
                'navigation_visible' => (bool) ($data['navigation_visible'] ?? true),
                'navigation_label' => $data['navigation_label'] ?? null,
                'sort_order' => (int) ($data['sort_order'] ?? 1000),
                'current_version' => 1,
                'created_by_member_id' => $actor->id,
                'updated_by_member_id' => $actor->id,
            ]);
            WebsitePageVersion::create(['website_page_id' => $page->id, 'version' => 1, 'schema' => WebsiteBuilderCatalog::starter($type), 'change_note' => 'Initial page', 'created_by_member_id' => $actor->id, 'created_at' => now()]);
            return $page->fresh();
        });
    }

    /** Saves page metadata and creates a new immutable schema version. */
    public function savePage(WebsitePage $page, WorkspaceMember $actor, array $data): WebsitePage
    {
        return DB::transaction(function () use ($page, $actor, $data) {
            $page = WebsitePage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            $schema = $this->normalizeSchema((array) ($data['schema'] ?? $this->versionSchema($page, $page->current_version)));
            $version = (int) $page->current_version + 1;
            $isHome = (bool) ($data['is_home'] ?? $page->is_home);
            if ($isHome) WebsitePage::query()->where('website_site_id', $page->website_site_id)->where('language', $data['language'] ?? $page->language)->where('id', '!=', $page->id)->update(['is_home' => false]);
            $slug = $isHome ? 'home' : $this->uniqueSlug($page->site, Str::slug((string) ($data['slug'] ?? $page->slug)) ?: 'page', (string) ($data['language'] ?? $page->language), $page->id);
            $page->update([
                'page_type' => $data['page_type'] ?? $page->page_type,
                'language' => $data['language'] ?? $page->language,
                'title' => Str::limit(trim((string) ($data['title'] ?? $page->title)), 180, ''),
                'slug' => $slug,
                'is_home' => $isHome,
                'navigation_visible' => (bool) ($data['navigation_visible'] ?? $page->navigation_visible),
                'navigation_label' => array_key_exists('navigation_label', $data) ? trim((string) $data['navigation_label']) ?: null : $page->navigation_label,
                'sort_order' => (int) ($data['sort_order'] ?? $page->sort_order),
                'seo_title' => array_key_exists('seo_title', $data) ? trim((string) $data['seo_title']) ?: null : $page->seo_title,
                'seo_description' => array_key_exists('seo_description', $data) ? trim((string) $data['seo_description']) ?: null : $page->seo_description,
                'og_media_id' => $data['og_media_id'] ?? $page->og_media_id,
                'current_version' => $version,
                'updated_by_member_id' => $actor->id,
            ]);
            WebsitePageVersion::create(['website_page_id' => $page->id, 'version' => $version, 'schema' => $schema, 'change_note' => Str::limit(trim((string) ($data['change_note'] ?? 'Page updated')), 500, ''), 'created_by_member_id' => $actor->id, 'created_at' => now()]);
            WebsitePageDraft::query()->where('website_page_id', $page->id)->delete();
            $this->syncReusableLinks($page, $schema);
            return $page->fresh();
        });
    }

    /** Stores one mutable autosave draft without incrementing immutable page history. */
    public function saveDraft(WebsitePage $page, WorkspaceMember $actor, array $data): WebsitePageDraft
    {
        $schema = $this->normalizeSchema((array) ($data['schema'] ?? $this->versionSchema($page, $page->current_version)));
        $metadata = $this->normalizeDraftMetadata($page, (array) ($data['metadata'] ?? []));
        $draft = DB::transaction(function () use ($page, $actor, $schema, $metadata) {
            $existing = WebsitePageDraft::query()->where('website_page_id', $page->id)->lockForUpdate()->first();
            if ($existing) {
                $existing->update(['schema' => $schema, 'metadata' => $metadata, 'revision' => $existing->revision + 1, 'updated_by_member_id' => $actor->id]);
                return $existing->fresh();
            }
            return WebsitePageDraft::create(['uuid' => (string) Str::uuid(), 'workspace_id' => $page->workspace_id, 'website_page_id' => $page->id, 'schema' => $schema, 'metadata' => $metadata, 'revision' => 1, 'updated_by_member_id' => $actor->id]);
        });
        $this->syncReusableLinks($page, $schema);
        return $draft;
    }

    /** Returns the latest mutable autosave draft in a stable editor payload. */
    public function draftPayload(WebsitePage $page): ?array
    {
        $draft = WebsitePageDraft::query()->where('website_page_id', $page->id)->first();
        if (! $draft) return null;
        return ['uuid' => $draft->uuid, 'revision' => $draft->revision, 'schema' => $draft->schema, 'metadata' => $draft->metadata ?: [], 'updated_at' => $draft->updated_at?->toISOString(), 'updated_by_member_id' => $draft->updated_by_member_id];
    }

    /** Discards only the mutable autosave, leaving immutable page history unchanged. */
    public function discardDraft(WebsitePage $page): void
    {
        WebsitePageDraft::query()->where('website_page_id', $page->id)->delete();
    }

    /** Runs server-side Website Studio preflight against an editor schema and page metadata. */
    public function preflightPage(WebsitePage $page, array $schema, array $metadata = []): array
    {
        $schema = $this->normalizeSchema($schema);
        $meta = array_merge(['title' => $page->title, 'seo_title' => $page->seo_title, 'seo_description' => $page->seo_description, 'og_media_id' => $page->og_media_id], $metadata);
        $issues = [];
        $add = static function (string $severity, string $code, string $message, ?string $sectionId = null) use (&$issues): void { $issues[] = compact('severity','code','message','sectionId'); };
        $sections = (array) ($schema['sections'] ?? []);
        if (! $sections) $add('error', 'page.empty', 'Add at least one section before publishing.');
        $ids = array_values(array_filter(array_map(fn ($section) => (string) ($section['id'] ?? ''), $sections)));
        if (count($ids) !== count(array_unique($ids))) $add('error', 'section.duplicate_id', 'Every section must have a unique ID.');
        $title = trim((string) ($meta['title'] ?? ''));
        if ($title === '') $add('error', 'page.title', 'Page title is required.');
        $seoTitle = trim((string) ($meta['seo_title'] ?? $title));
        $seoDescription = trim((string) ($meta['seo_description'] ?? ''));
        if (strlen($seoTitle) < 20) $add('warning', 'seo.title_short', 'SEO title is very short.');
        if (strlen($seoTitle) > 65) $add('warning', 'seo.title_long', 'SEO title is longer than 65 characters.');
        if (strlen($seoDescription) < 70) $add('warning', 'seo.description_short', 'Add a useful SEO description of roughly 70–160 characters.');
        if (strlen($seoDescription) > 170) $add('warning', 'seo.description_long', 'SEO description is longer than 170 characters.');
        $activeForms = WebsiteForm::query()->where('website_site_id', $page->website_site_id)->where('status', 'active')->pluck('uuid')->all();
        foreach ($sections as $section) {
            $sectionId = (string) ($section['id'] ?? '');
            $type = (string) ($section['type'] ?? 'custom');
            $settings = (array) ($section['settings'] ?? []);
            if ($type === 'form' && (empty($settings['form_uuid']) || ! in_array((string) $settings['form_uuid'], $activeForms, true))) $add('error', 'form.missing', 'Connect this form section to an active website form.', $sectionId);
            if ($type === 'image' && empty($settings['media_id'])) $add('error', 'media.image_missing', 'Choose an image for this image section.', $sectionId);
            if ($type === 'gallery' && empty($settings['media_ids'])) $add('warning', 'media.gallery_empty', 'Add at least one image to this gallery.', $sectionId);
            foreach (['primary_url','secondary_url','button_url'] as $key) if (! empty($settings[$key]) && ! $this->safeEditorUrl((string) $settings[$key])) $add('error', 'url.unsafe', 'Use an http(s), mailto, tel, anchor or site-relative URL.', $sectionId);
        }
        foreach ($this->dynamicTokens($schema) as $token) {
            if (! in_array($token, array_keys($this->dynamicBindingContext($page)), true)) $add('warning', 'binding.unknown', 'Unknown dynamic token {{'.$token.'}} will render unchanged.');
        }
        $mediaIds = $this->mediaIds($schema);
        if (! empty($meta['og_media_id']) && is_numeric($meta['og_media_id'])) $mediaIds[] = (int) $meta['og_media_id'];
        $mediaIds = array_values(array_unique($mediaIds));
        if ($mediaIds) {
            $assets = MediaAsset::query()->where('workspace_id', $page->workspace_id)->whereIn('id', $mediaIds)->whereNull('deleted_at')->get(['id','mime_type','alt_text','license_expires_at','rights_review_at','copyright_owner','license_type','license_reference'])->keyBy('id');
            foreach ($mediaIds as $mediaId) {
                $asset = $assets->get($mediaId);
                if (! $asset) { $add('error', 'media.missing', 'A referenced Media Library asset is missing or unavailable.'); continue; }
                if ($asset->rightsStatus() === 'expired') $add('error', 'media.rights_expired', 'A referenced media asset has an expired license.');
                if (in_array($asset->rightsStatus(), ['review','expiring','unclassified'], true)) $add('warning', 'media.rights_attention', 'A referenced media asset needs rights review.');
                if (str_starts_with((string) $asset->mime_type, 'image/') && trim((string) $asset->alt_text) === '') $add('warning', 'media.alt_missing', 'An image referenced by this page is missing Media Library alt text.');
            }
        }
        return ['ready' => ! collect($issues)->contains(fn ($issue) => $issue['severity'] === 'error'), 'issues' => $issues, 'summary' => ['errors' => collect($issues)->where('severity', 'error')->count(), 'warnings' => collect($issues)->where('severity', 'warning')->count(), 'sections' => count($sections), 'media_assets' => count($mediaIds)]];
    }

    /** Converts the exact in-memory editor state into an immutable staging version after successful preflight. */
    public function stagePage(WebsitePage $page, WorkspaceMember $actor, array $data): WebsitePage
    {
        abort_if($page->status === 'archived', 422, 'Restore this page before staging it for review.');
        $schema = $this->normalizeSchema((array) ($data['schema'] ?? []));
        $metadata = $this->normalizeDraftMetadata($page, (array) ($data['metadata'] ?? []));
        $preflight = $this->preflightPage($page, $schema, $metadata);
        abort_unless($preflight['ready'], 422, 'Website preflight contains blocking issues.');
        $staged = $this->savePage($page, $actor, array_merge($metadata, ['schema' => $schema, 'change_note' => 'Staged for review']));
        $staged->update(['staged_version' => $staged->current_version, 'staged_at' => now(), 'updated_by_member_id' => $actor->id]);
        return $staged->fresh();
    }

    /** Creates one revocable share token for the page's current immutable staging version. */
    public function createPreviewToken(WebsitePage $page, WorkspaceMember $actor, int $expiresHours = 72): array
    {
        $version = (int) ($page->staged_version ?: 0);
        abort_if($version < 1, 422, 'Stage this page before creating a shareable preview.');
        abort_unless($this->versionSchema($page, $version), 422, 'The staged page version could not be found.');
        $raw = Str::random(64);
        $record = WebsitePreviewToken::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $page->workspace_id, 'website_site_id' => $page->website_site_id,
            'website_page_id' => $page->id, 'token_hash' => hash('sha256', $raw), 'source' => 'staging', 'version' => $version,
            'created_by_member_id' => $actor->id, 'expires_at' => now()->addHours(max(1, min(168, $expiresHours))), 'created_at' => now(),
        ]);
        return ['uuid' => $record->uuid, 'url' => '/site-preview/'.$raw, 'version' => $version, 'expires_at' => $record->expires_at?->toIso8601String()];
    }

    /** Revokes one Website Studio preview token without changing the staged page. */
    public function revokePreviewToken(WebsitePreviewToken $token): void
    {
        if (! $token->revoked_at) $token->update(['revoked_at' => now()]);
    }

    /** Resolves one unexpired staging share token to the same public renderer contract used by live delivery. */
    public function previewPayload(string $rawToken): array
    {
        abort_if(strlen($rawToken) < 32 || strlen($rawToken) > 160, 404);
        $token = WebsitePreviewToken::query()->where('token_hash', hash('sha256', $rawToken))->with(['page.site.workspace'])->firstOrFail();
        abort_if($token->revoked_at || ! $token->expires_at || $token->expires_at->isPast(), 404);
        $page = $token->page;
        $site = $token->site ?: $page?->site;
        abort_unless($page && $site && (int) $page->website_site_id === (int) $site->id, 404);
        $schema = $this->versionSchema($page, (int) $token->version);
        abort_unless($schema, 404);
        $token->forceFill(['last_viewed_at' => now()])->save();
        $payload = $this->buildPagePayload($site, $page, $schema, true);
        $payload['preview'] = ['uuid' => $token->uuid, 'version' => $token->version, 'expires_at' => $token->expires_at?->toIso8601String()];
        return $payload;
    }

    /** Returns page review comments with creator/resolution metadata. */
    public function pageComments(WebsitePage $page): array
    {
        return WebsitePageComment::query()->where('website_page_id', $page->id)->with(['createdBy.user:id,first_name,last_name,email','resolvedBy.user:id,first_name,last_name,email'])->latest()->limit(250)->get()->map(fn (WebsitePageComment $comment) => [
            'id' => $comment->id, 'uuid' => $comment->uuid, 'section_id' => $comment->section_id, 'message' => $comment->message, 'status' => $comment->status,
            'created_by' => trim((string) $comment->createdBy?->user?->first_name.' '.(string) $comment->createdBy?->user?->last_name) ?: $comment->createdBy?->user?->email,
            'created_at' => $comment->created_at?->toIso8601String(), 'resolved_at' => $comment->resolved_at?->toIso8601String(),
        ])->all();
    }

    /** Creates one page- or section-scoped review comment. */
    public function createPageComment(WebsitePage $page, WorkspaceMember $actor, array $data): WebsitePageComment
    {
        $sectionId = trim((string) ($data['section_id'] ?? '')) ?: null;
        if ($sectionId) {
            $schema = (array) ($page->draft?->schema ?: $this->versionSchema($page, $page->current_version));
            abort_unless(collect((array) ($schema['sections'] ?? []))->contains(fn ($section) => (string) ($section['id'] ?? '') === $sectionId), 422, 'The selected section no longer exists.');
        }
        return WebsitePageComment::create(['uuid' => (string) Str::uuid(), 'workspace_id' => $page->workspace_id, 'website_page_id' => $page->id, 'section_id' => $sectionId, 'message' => Str::limit(trim((string) $data['message']), 5000, ''), 'status' => 'open', 'created_by_member_id' => $actor->id]);
    }

    /** Resolves or reopens one review comment without deleting its history. */
    public function updatePageComment(WebsitePageComment $comment, WorkspaceMember $actor, string $status): WebsitePageComment
    {
        abort_unless(in_array($status, ['open','resolved'], true), 422);
        $comment->update(['status' => $status, 'resolved_at' => $status === 'resolved' ? now() : null, 'resolved_by_member_id' => $status === 'resolved' ? $actor->id : null]);
        return $comment->fresh();
    }

    /** Publishes the current immutable page version without exposing later drafts. */
    public function publishPage(WebsitePage $page, WorkspaceMember $actor): WebsitePage
    {
        return DB::transaction(function () use ($page, $actor) {
            $targetVersion = (int) ($page->staged_version ?: $page->current_version);
            $schema = $this->versionSchema($page, $targetVersion);
            abort_unless($schema, 422, 'The staged page version could not be found.');
            $preflight = $this->preflightPage($page, $schema);
            abort_unless($preflight['ready'], 422, 'Website preflight contains blocking issues.');
            $page->versions()->where('version', $targetVersion)->update(['published_at' => now()]);
            $page->update(['status' => 'published', 'published_version' => $targetVersion, 'published_at' => now(), 'staged_version' => null, 'staged_at' => null, 'updated_by_member_id' => $actor->id]);
            $this->syncPublishedMedia($page, $schema, $actor);
            if ($page->site->status !== 'published') $page->site->update(['status' => 'published', 'published_at' => now(), 'updated_by_member_id' => $actor->id]);
            $this->webhooks->queueEvent($page->workspace, 'website.page_published', ['website_page_id' => $page->id, 'page_uuid' => $page->uuid, 'title' => $page->title, 'slug' => $page->slug, 'language' => $page->language, 'version' => $targetVersion]);
            return $page->fresh();
        });
    }

    /** Restores an old revision by copying it into a new current version rather than mutating history. */
    public function restoreVersion(WebsitePage $page, WebsitePageVersion $version, WorkspaceMember $actor): WebsitePage
    {
        abort_unless((int) $version->website_page_id === (int) $page->id, 404);
        return $this->savePage($page, $actor, ['schema' => $version->schema, 'change_note' => 'Restored from version '.$version->version]);
    }

    /** Saves a reusable section for later insertion into any workspace page. */
    public function saveReusableSection(WebsiteSite $site, WorkspaceMember $actor, array $data, ?WebsiteReusableSection $section = null): WebsiteReusableSection
    {
        $schema = $this->normalizeSection((array) ($data['schema'] ?? []));
        unset($schema['settings']['linked_reusable_uuid']);
        if ($section) {
            abort_unless((int) $section->workspace_id === (int) $site->workspace_id, 404);
            $section->update(['name' => Str::limit(trim((string) $data['name']), 160, ''), 'section_type' => $schema['type'], 'schema' => $schema, 'is_global' => (bool) ($data['is_global'] ?? false), 'updated_by_member_id' => $actor->id]);
            $fresh = $section->fresh();
            if ($fresh->is_global) $this->propagateReusableSection($fresh, $actor);
            else WebsiteReusableSectionLink::query()->where('website_reusable_section_id', $fresh->id)->delete();
            return $fresh;
        }
        return WebsiteReusableSection::create(['uuid' => (string) Str::uuid(), 'workspace_id' => $site->workspace_id, 'name' => Str::limit(trim((string) $data['name']), 160, ''), 'section_type' => $schema['type'], 'schema' => $schema, 'is_global' => (bool) ($data['is_global'] ?? false), 'created_by_member_id' => $actor->id, 'updated_by_member_id' => $actor->id]);
    }

    /** Creates or updates one website lead form with a normalized field schema. */
    public function saveForm(WebsiteSite $site, WorkspaceMember $actor, array $data, ?WebsiteForm $form = null): WebsiteForm
    {
        $fields = collect((array) ($data['fields'] ?? []))->take(30)->map(function ($field, $index) {
            $field = is_array($field) ? $field : [];
            $type = in_array($field['type'] ?? 'text', ['text','email','phone','textarea','select','checkbox','number','date'], true) ? $field['type'] : 'text';
            return ['id' => Str::slug((string) ($field['id'] ?? $field['label'] ?? 'field-'.$index), '_') ?: 'field_'.$index, 'type' => $type, 'label' => Str::limit(trim((string) ($field['label'] ?? 'Field')), 120, ''), 'required' => (bool) ($field['required'] ?? false), 'options' => array_values(array_slice(array_map('strval', (array) ($field['options'] ?? [])), 0, 50))];
        })->values()->all();
        abort_if(! $fields, 422, 'Add at least one form field.');
        if (! empty($data['website_page_id'])) WebsitePage::query()->where('workspace_id', $site->workspace_id)->findOrFail((int) $data['website_page_id']);
        $payload = [
            'website_site_id' => $site->id,
            'website_page_id' => $data['website_page_id'] ?? null,
            'name' => Str::limit(trim((string) $data['name']), 160, ''),
            'slug' => Str::slug((string) ($data['slug'] ?? $data['name'])) ?: 'form-'.Str::lower(Str::random(6)),
            'status' => $data['status'] ?? 'active',
            'fields' => $fields,
            'settings' => (array) ($data['settings'] ?? ['require_consent' => false]),
            'success_message' => trim((string) ($data['success_message'] ?? 'Thanks. Your message has been received.')),
            'notification_emails' => array_values(array_unique(array_filter(array_map('strval', (array) ($data['notification_emails'] ?? []))))),
        ];
        if ($form) {
            abort_unless((int) $form->workspace_id === (int) $site->workspace_id, 404);
            $duplicate = WebsiteForm::query()->where('website_site_id', $site->id)->where('slug', $payload['slug'])->where('id', '!=', $form->id)->exists();
            abort_if($duplicate, 422, 'A website form with this slug already exists.');
            $form->update($payload);
            return $form->fresh();
        }
        abort_if(WebsiteForm::query()->where('website_site_id', $site->id)->where('slug', $payload['slug'])->exists(), 422, 'A website form with this slug already exists.');
        return WebsiteForm::create(array_merge($payload, ['uuid' => (string) Str::uuid(), 'workspace_id' => $site->workspace_id, 'created_by_member_id' => $actor->id]));
    }

    /** Captures one public lead after validating fields against the form definition and hashing network identifiers. */
    public function submitForm(WebsiteForm $form, Request $request): WebsiteFormSubmission
    {
        abort_unless($form->status === 'active' && $form->site?->status === 'published', 404);
        abort_unless($form->site?->workspace && $this->entitlements->allows($form->site->workspace, 'feature.website_forms'), 404);
        abort_if(trim((string) $request->input('_company_website')) !== '', 422, 'Submission rejected.');
        $input = (array) $request->input('fields', []);
        $clean = [];
        $errors = [];
        foreach ((array) $form->fields as $field) {
            $id = (string) $field['id'];
            $value = $input[$id] ?? null;
            if (($field['required'] ?? false) && ($value === null || $value === '' || $value === false)) $errors[$id][] = ($field['label'] ?? $id).' is required.';
            if (($field['type'] ?? '') === 'email' && $value && ! filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[$id][] = 'Enter a valid email address.';
            if (is_string($value)) $value = Str::limit(trim($value), ($field['type'] ?? '') === 'textarea' ? 5000 : 500, '');
            if (($field['type'] ?? '') === 'select' && $value !== null && ! in_array((string) $value, (array) ($field['options'] ?? []), true)) $errors[$id][] = 'Choose a valid option.';
            $clean[$id] = $value;
        }
        if ($errors) throw ValidationException::withMessages($errors);
        $requireConsent = (bool) data_get($form->settings, 'require_consent', false);
        $consent = (bool) $request->boolean('consent');
        abort_if($requireConsent && ! $consent, 422, 'Consent is required before submitting this form.');
        $salt = (string) config('app.key').':'.$form->workspace_id;
        $submission = WebsiteFormSubmission::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $form->workspace_id,
            'website_form_id' => $form->id,
            'website_page_id' => $form->website_page_id,
            'payload' => $clean,
            'status' => 'new',
            'consent' => $consent,
            'source_url' => Str::limit((string) $request->input('source_url', ''), 1000, '') ?: null,
            'ip_hash' => $request->ip() ? hash('sha256', $salt.':'.$request->ip()) : null,
            'user_agent_hash' => $request->userAgent() ? hash('sha256', $salt.':'.$request->userAgent()) : null,
            'submitted_at' => now(),
        ]);
        $this->notifyLead($form, $submission);
        $this->webhooks->queueEvent($form->workspace, 'website.lead_received', ['website_form_id' => $form->id, 'form_uuid' => $form->uuid, 'submission_id' => $submission->id, 'submission_uuid' => $submission->uuid]);
        return $submission;
    }

    /** Resolves one published page payload for a workspace slug, host or language request. */
    public function publicPayload(WebsiteSite $site, string $path, ?string $language = null): array
    {
        abort_unless($site->status === 'published', 404);
        $language = strtolower($language ?: $site->default_language ?: 'en');
        $path = trim($path, '/');
        $pageQuery = WebsitePage::query()->where('website_site_id', $site->id)->where('status', 'published')->whereNotNull('published_version');
        $page = $path === '' || $path === 'home'
            ? (clone $pageQuery)->where('language', $language)->where('is_home', true)->first()
            : (clone $pageQuery)->where('language', $language)->where('slug', $path)->first();
        if (! $page && $language !== $site->default_language) {
            $page = $path === '' || $path === 'home'
                ? (clone $pageQuery)->where('language', $site->default_language)->where('is_home', true)->first()
                : (clone $pageQuery)->where('language', $site->default_language)->where('slug', $path)->first();
        }
        abort_unless($page, 404);
        return $this->buildPagePayload($site, $page, $this->versionSchema($page, (int) $page->published_version));
    }

    /** Builds one renderer payload for published delivery or staging preview using the same schema resolver. */
    private function buildPagePayload(WebsiteSite $site, WebsitePage $page, array $schema, bool $preview = false): array
    {
        $bound = $this->bindDynamicValues($schema, $this->dynamicBindingContext($page));
        $schema = $this->publicSchema((array) $bound);
        $navigation = WebsitePage::query()->where('website_site_id', $site->id)->where('status', 'published')->where('language', $page->language)->where('navigation_visible', true)->orderBy('sort_order')->orderBy('title')->get(['title','slug','is_home','navigation_label'])->map(fn ($item) => ['label' => $item->navigation_label ?: $item->title, 'path' => $item->is_home ? '/' : '/'.$item->slug])->all();
        return [
            'site' => ['uuid' => $site->uuid, 'workspace_slug' => $site->workspace?->slug, 'name' => $site->name, 'default_language' => $site->default_language, 'supported_languages' => $site->supported_languages ?: [$site->default_language], 'theme' => $site->theme ?: WebsiteBuilderCatalog::theme(), 'header_config' => $site->header_config ?: WebsiteBuilderCatalog::header(), 'footer_config' => $site->footer_config ?: WebsiteBuilderCatalog::footer(), 'seo_defaults' => $site->seo_defaults ?: []],
            'page' => ['uuid' => $page->uuid, 'type' => $page->page_type, 'language' => $page->language, 'title' => $page->title, 'slug' => $page->slug, 'seo_title' => $page->seo_title, 'seo_description' => $page->seo_description, 'og_image' => $page->og_media_id ? $this->publicMedia((int) $page->og_media_id) : null, 'schema' => $schema],
            'navigation' => $navigation,
            'forms' => $site->workspace && $this->entitlements->allows($site->workspace, 'feature.website_forms') ? WebsiteForm::query()->where('website_site_id', $site->id)->where('status', 'active')->get(['uuid','name','fields','settings','success_message'])->keyBy('uuid')->map(fn ($form) => ['uuid' => $form->uuid, 'name' => $form->name, 'fields' => $form->fields, 'settings' => $form->settings ?: [], 'success_message' => $form->success_message])->all() : [],
            'is_preview' => $preview,
        ];
    }

    /** Returns the allowlisted dynamic Website Studio values available to authored content. */
    private function dynamicBindingContext(WebsitePage $page): array
    {
        return ['site.name' => (string) $page->site?->name, 'page.title' => (string) $page->title, 'page.slug' => (string) $page->slug, 'page.language' => (string) $page->language, 'year' => now()->format('Y')];
    }

    /** Recursively replaces allowlisted {{token}} expressions while leaving unknown tokens visible for review. */
    private function bindDynamicValues(mixed $value, array $context): mixed
    {
        if (is_array($value)) return array_map(fn ($item) => $this->bindDynamicValues($item, $context), $value);
        if (! is_string($value) || ! str_contains($value, '{{')) return $value;
        return preg_replace_callback('/\{\{\s*([a-z0-9_.-]+)\s*\}\}/i', static fn (array $match) => array_key_exists($match[1], $context) ? (string) $context[$match[1]] : $match[0], $value) ?? $value;
    }

    /** Extracts unique dynamic token names from one editor schema for preflight validation. */
    private function dynamicTokens(mixed $value): array
    {
        $tokens = [];
        $walk = function (mixed $item) use (&$walk, &$tokens): void {
            if (is_array($item)) { foreach ($item as $child) $walk($child); return; }
            if (! is_string($item) || ! str_contains($item, '{{')) return;
            preg_match_all('/\{\{\s*([a-z0-9_.-]+)\s*\}\}/i', $item, $matches);
            foreach ((array) ($matches[1] ?? []) as $token) $tokens[] = (string) $token;
        };
        $walk($value);
        return array_values(array_unique($tokens));
    }

    /** Synchronizes the materialized link index for reusable components referenced by one page schema. */
    private function syncReusableLinks(WebsitePage $page, array $schema): void
    {
        $sections = collect((array) ($schema['sections'] ?? []))->filter(fn ($section) => is_array($section) && ! empty($section['settings']['linked_reusable_uuid']));
        $uuids = $sections->map(fn ($section) => (string) $section['settings']['linked_reusable_uuid'])->unique()->values();
        $components = WebsiteReusableSection::query()->where('workspace_id', $page->workspace_id)->whereIn('uuid', $uuids)->where('is_global', true)->get(['id','uuid'])->keyBy('uuid');
        WebsiteReusableSectionLink::query()->where('website_page_id', $page->id)->delete();
        foreach ($sections as $section) {
            $component = $components->get((string) $section['settings']['linked_reusable_uuid']);
            if (! $component) continue;
            WebsiteReusableSectionLink::create(['workspace_id' => $page->workspace_id, 'website_page_id' => $page->id, 'website_reusable_section_id' => $component->id, 'instance_id' => (string) $section['id']]);
        }
    }

    /** Propagates one global reusable component into mutable drafts without rewriting immutable/published history. */
    private function propagateReusableSection(WebsiteReusableSection $component, WorkspaceMember $actor): void
    {
        $links = WebsiteReusableSectionLink::query()->where('website_reusable_section_id', $component->id)->with('page.draft')->get();
        foreach ($links as $link) {
            $page = $link->page;
            if (! $page) continue;
            $draft = $page->draft;
            $schema = (array) ($draft?->schema ?: $this->versionSchema($page, $page->current_version));
            $changed = false;
            $schema['sections'] = array_map(function ($section) use ($link, $component, &$changed) {
                if (! is_array($section) || (string) ($section['id'] ?? '') !== (string) $link->instance_id) return $section;
                $replacement = $this->normalizeSection((array) $component->schema);
                $replacement['id'] = (string) $link->instance_id;
                $replacement['settings']['linked_reusable_uuid'] = $component->uuid;
                $changed = true;
                return $replacement;
            }, (array) ($schema['sections'] ?? []));
            if (! $changed) continue;
            $metadata = $draft?->metadata ?: $this->normalizeDraftMetadata($page, []);
            $this->saveDraft($page, $actor, ['schema' => $schema, 'metadata' => $metadata]);
        }
    }

    /** Returns the immutable schema stored for one specific page version. */
    public function versionSchema(WebsitePage $page, int $version): array
    {
        return (array) (WebsitePageVersion::query()->where('website_page_id', $page->id)->where('version', $version)->value('schema') ?? []);
    }

    /** Normalizes a complete editor page schema to the supported section catalog and hard limits. */
    private function normalizeSchema(array $schema): array
    {
        $sections = collect((array) ($schema['sections'] ?? []))->take(100)->map(fn ($section) => $this->normalizeSection(is_array($section) ? $section : []))->values()->all();
        return ['schema_version' => 1, 'sections' => $sections];
    }

    /** Normalizes one section while retaining safe JSON settings used by the visual editor. */
    private function normalizeSection(array $section): array
    {
        $type = in_array($section['type'] ?? 'custom', WebsiteBuilderCatalog::SECTION_TYPES, true) ? $section['type'] : 'custom';
        $settings = $this->normalizeValue((array) ($section['settings'] ?? []), 0);
        return ['id' => preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($section['id'] ?? 'section_'.Str::lower(Str::random(8)))) ?: 'section_'.Str::lower(Str::random(8)), 'type' => $type, 'settings' => $settings];
    }

    /** Recursively bounds user-controlled section JSON while preserving scalar layout/content values. */
    private function normalizeValue(mixed $value, int $depth): mixed
    {
        if ($depth > 5) return null;
        if (is_array($value)) {
            $out = [];
            foreach (array_slice($value, 0, 100, true) as $key => $item) {
                if (is_string($key) && (str_starts_with($key, '_') || in_array($key, ['media','media_items','content_url','download_url','public_url','path','disk'], true))) continue;
                $out[is_int($key) ? $key : Str::limit((string) $key, 80, '')] = $this->normalizeValue($item, $depth + 1);
            }
            return $out;
        }
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) return $value;
        $text = Str::limit((string) $value, 20000, '');
        return str_contains(strtolower((string) $text), '<') ? $this->sanitizeRichText($text) : $text;
    }

    /** Sanitizes rich text without requiring the optional DOM PHP extension. */
    private function sanitizeRichText(string $html): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><a><ul><ol><li><h1><h2><h3><h4><blockquote><code>');
        $html = preg_replace('/\s(?:on[a-z]+|style)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace_callback('/\s(href)\s*=\s*(["\'])(.*?)\2/i', static function (array $match): string {
            $url = trim(html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($url === '' || preg_match('/^(?:https?:|mailto:|tel:|\/|#)/i', $url)) return ' href="'.htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
            return '';
        }, $html) ?? '';
        return $html;
    }

    /** Normalizes page metadata stored with a mutable autosave draft. */
    private function normalizeDraftMetadata(WebsitePage $page, array $metadata): array
    {
        return [
            'title' => Str::limit(trim((string) ($metadata['title'] ?? $page->title)), 180, ''),
            'slug' => Str::limit(trim((string) ($metadata['slug'] ?? $page->slug)), 180, ''),
            'language' => Str::limit(strtolower((string) ($metadata['language'] ?? $page->language)), 12, ''),
            'is_home' => (bool) ($metadata['is_home'] ?? $page->is_home),
            'navigation_visible' => (bool) ($metadata['navigation_visible'] ?? $page->navigation_visible),
            'navigation_label' => trim((string) ($metadata['navigation_label'] ?? $page->navigation_label)) ?: null,
            'seo_title' => trim((string) ($metadata['seo_title'] ?? $page->seo_title)) ?: null,
            'seo_description' => Str::limit(trim((string) ($metadata['seo_description'] ?? $page->seo_description)), 1000, '') ?: null,
            'og_media_id' => ! empty($metadata['og_media_id']) ? (int) $metadata['og_media_id'] : null,
        ];
    }

    /** Accepts only public-safe website URLs supported by the renderer. */
    private function safeEditorUrl(string $value): bool
    {
        $value = trim($value);
        return $value === '' || (bool) preg_match('/^(?:https?:|mailto:|tel:|\/|#)/i', $value);
    }

    /** Creates a collision-free localized page slug inside one website. */
    private function uniqueSlug(WebsiteSite $site, string $base, string $language, ?int $exceptId = null): string
    {
        $candidate = Str::limit($base, 180, '') ?: 'page';
        $suffix = 2;
        while (WebsitePage::query()->where('website_site_id', $site->id)->where('language', $language)->where('slug', $candidate)->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))->exists()) $candidate = Str::limit($base, 170, '').'-'.$suffix++;
        return $candidate;
    }

    /** Registers public Media Library dependencies referenced by the published page revision. */
    private function syncPublishedMedia(WebsitePage $page, array $schema, WorkspaceMember $actor): void
    {
        $previous = MediaUsage::query()->where('workspace_id', $page->workspace_id)->where('resource_type', 'website_page')->where('resource_id', $page->id)->pluck('media_asset_id')->all();
        MediaUsage::query()->where('workspace_id', $page->workspace_id)->where('resource_type', 'website_page')->where('resource_id', $page->id)->delete();
        $ids = $this->mediaIds($schema);
        if ($page->og_media_id) $ids[] = (int) $page->og_media_id;
        $ids = array_values(array_unique($ids));
        foreach (MediaAsset::query()->where('workspace_id', $page->workspace_id)->whereIn('id', $ids)->get() as $asset) {
            $asset->update(['visibility' => 'public']);
            $this->media->registerUsage($asset, 'website_page', $page->id, 'published_schema', $page->title.' website page', $actor->user_id);
        }
        foreach (array_diff($previous, $ids) as $assetId) {
            $asset = MediaAsset::query()->find($assetId);
            if ($asset && $asset->visibility === 'public' && ! $asset->usages()->exists()) $asset->update(['visibility' => 'private']);
        }
    }

    /** Extracts unique Media Library IDs from arbitrary nested page schema values. */
    private function mediaIds(array $schema): array
    {
        $ids = [];
        $walk = function ($value, $key = null) use (&$walk, &$ids) {
            if ((string) $key === 'media_ids' && is_array($value)) { foreach ($value as $id) if (is_numeric($id)) $ids[] = (int) $id; return; }
            if (in_array((string) $key, ['media_id','image_id','og_media_id'], true) && is_numeric($value)) { $ids[] = (int) $value; return; }
            if (is_array($value)) foreach ($value as $childKey => $child) $walk($child, $childKey);
        };
        $walk($schema);
        return array_values(array_unique(array_filter($ids)));
    }

    /** Converts private editor media references into safe public website URLs. */
    private function publicSchema(array $schema): array
    {
        $ids = $this->mediaIds($schema);
        $media = MediaAsset::query()->whereIn('id', $ids)->where('visibility', 'public')->whereNull('deleted_at')->get(['id','uuid','name','mime_type','alt_text','caption','width','height'])->keyBy('id');
        $walk = function ($value, $key = null) use (&$walk, $media) {
            if (is_array($value)) {
                $out = [];
                foreach ($value as $childKey => $child) $out[$childKey] = $walk($child, $childKey);
                if (isset($out['media_id']) && is_numeric($out['media_id']) && ($asset = $media->get((int) $out['media_id']))) $out['media'] = $this->mediaPayload($asset);
                if (isset($out['media_ids']) && is_array($out['media_ids'])) $out['media_items'] = collect($out['media_ids'])->map(fn ($id) => $media->get((int) $id))->filter()->map(fn ($asset) => $this->mediaPayload($asset))->values()->all();
                return $out;
            }
            return $value;
        };
        return $walk($schema);
    }

    /** Returns a public media payload without exposing storage disk or path metadata. */
    private function mediaPayload(MediaAsset $asset): array
    {
        return ['id' => $asset->id, 'uuid' => $asset->uuid, 'name' => $asset->name, 'mime_type' => $asset->mime_type, 'alt_text' => $asset->alt_text, 'caption' => $asset->caption, 'width' => $asset->width, 'height' => $asset->height, 'url' => '/api/v1/media/public/'.$asset->uuid];
    }

    /** Returns one public media payload by ID when the asset is publishable. */
    private function publicMedia(int $assetId): ?array
    {
        $asset = MediaAsset::query()->whereKey($assetId)->where('visibility', 'public')->whereNull('deleted_at')->first();
        return $asset ? $this->mediaPayload($asset) : null;
    }

    /** Sends a workspace notification about a newly captured website lead. */
    private function notifyLead(WebsiteForm $form, WebsiteFormSubmission $submission): void
    {
        $workspace = $form->site?->workspace;
        if (! $workspace) return;
        $targets = WorkspaceMember::query()->with('user')->where('workspace_id', $workspace->id)->where('status', 'active')->get()->filter(fn ($member) => $member->hasPermission('website.submissions_view') || $member->hasPermission('website.manage'));
        foreach ($targets as $member) if ($member->user) $this->notifications->notify($workspace, $member->user, 'website', 'website.lead_received', 'New website lead', 'A new submission was received from '.$form->name.'.', 'info', ['website_form_id' => $form->id, 'submission_id' => $submission->id]);
    }
}
