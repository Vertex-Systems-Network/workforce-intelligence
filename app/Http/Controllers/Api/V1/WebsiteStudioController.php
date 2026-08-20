<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebsiteForm;
use App\Models\WebsiteFormSubmission;
use App\Models\WebsitePage;
use App\Models\WebsitePageVersion;
use App\Models\WebsitePageComment;
use App\Models\WebsitePreviewToken;
use App\Models\WebsiteReusableSection;
use App\Services\WebsiteBuilderService;
use App\Support\DataGridRequest;
use App\Support\LocaleCatalog;
use App\Support\WebsiteBuilderCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Provides authenticated Website Studio management endpoints for one workspace. */
class WebsiteStudioController extends Controller
{
    /** Returns the complete Website Studio editor payload. */
    public function overview(Request $request, WebsiteBuilderService $service): JsonResponse
    {
        return response()->json($service->overview($request->attributes->get('workspace'), $request->attributes->get('workspaceMember')));
    }

    /** Updates website-wide theme, navigation, languages, footer and SEO configuration. */
    public function updateSite(Request $request, WebsiteBuilderService $service): JsonResponse
    {
        $site = $service->ensureSite($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'));
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:160',
            'status' => ['sometimes', Rule::in(['draft','published','offline'])],
            'default_language' => ['sometimes', Rule::in(LocaleCatalog::SUPPORTED)],
            'supported_languages' => 'sometimes|array|min:1|max:10',
            'supported_languages.*' => [Rule::in(LocaleCatalog::SUPPORTED)],
            'theme' => 'sometimes|array',
            'header_config' => 'sometimes|array',
            'footer_config' => 'sometimes|array',
            'seo_defaults' => 'sometimes|array',
            'custom_domain_id' => 'nullable|integer',
        ]);
        return response()->json(['data' => $service->updateSite($site, $request->attributes->get('workspaceMember'), $data), 'message' => 'Website settings saved.']);
    }

    /** Creates a new localized page with an immutable starter version. */
    public function storePage(Request $request, WebsiteBuilderService $service): JsonResponse
    {
        $site = $service->ensureSite($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'));
        $data = $request->validate([
            'page_type' => ['required', Rule::in(WebsiteBuilderCatalog::PAGE_TYPES)],
            'title' => 'required|string|max:180',
            'slug' => 'nullable|string|max:180',
            'language' => ['required', Rule::in(LocaleCatalog::SUPPORTED)],
            'is_home' => 'sometimes|boolean',
            'navigation_visible' => 'sometimes|boolean',
            'navigation_label' => 'nullable|string|max:120',
        ]);
        return response()->json(['data' => $service->createPage($site, $request->attributes->get('workspaceMember'), $data), 'message' => 'Page created.'], 201);
    }

    /** Returns page metadata, current schema and immutable version history. */
    public function showPage(Request $request, WebsitePage $page, WebsiteBuilderService $service): JsonResponse
    {
        $this->ensurePage($request, $page);
        return response()->json([
            'page' => $page->load('ogMedia:id,uuid,name,mime_type,alt_text'),
            'schema' => $service->versionSchema($page, (int) $page->current_version),
            'draft' => $service->draftPayload($page),
            'comments' => $service->pageComments($page),
            'preview_tokens' => WebsitePreviewToken::query()->where('website_page_id', $page->id)->latest('created_at')->limit(30)->get(['id','uuid','source','version','expires_at','revoked_at','last_viewed_at','created_at']),
            'versions' => $page->versions()->latest('version')->limit(100)->get(['id','version','change_note','created_by_member_id','published_at','created_at']),
        ]);
    }

    /** Saves page metadata and creates a new immutable draft schema version. */
    public function updatePage(Request $request, WebsitePage $page, WebsiteBuilderService $service): JsonResponse
    {
        $this->ensurePage($request, $page);
        $data = $request->validate([
            'page_type' => ['sometimes', Rule::in(WebsiteBuilderCatalog::PAGE_TYPES)],
            'title' => 'sometimes|required|string|max:180',
            'slug' => 'sometimes|required|string|max:180',
            'language' => ['sometimes', Rule::in(LocaleCatalog::SUPPORTED)],
            'is_home' => 'sometimes|boolean',
            'navigation_visible' => 'sometimes|boolean',
            'navigation_label' => 'nullable|string|max:120',
            'sort_order' => 'sometimes|integer|min:0|max:65000',
            'seo_title' => 'nullable|string|max:180',
            'seo_description' => 'nullable|string|max:1000',
            'og_media_id' => 'nullable|integer',
            'schema' => 'required|array',
            'change_note' => 'nullable|string|max:500',
        ]);
        if (! empty($data['og_media_id'])) {
            \App\Models\MediaAsset::query()->where('workspace_id', $page->workspace_id)->whereNull('deleted_at')->findOrFail((int) $data['og_media_id']);
        }
        return response()->json(['data' => $service->savePage($page, $request->attributes->get('workspaceMember'), $data), 'message' => 'Page saved as a new version.']);
    }

    /** Stores the latest mutable editor draft without creating an immutable version. */
    public function autosaveDraft(Request $request, WebsitePage $page, WebsiteBuilderService $service): JsonResponse
    {
        $this->ensurePage($request, $page);
        $data = $request->validate(['schema' => 'required|array', 'metadata' => 'nullable|array']);
        if (! empty($data['metadata']['og_media_id'])) {
            \App\Models\MediaAsset::query()->where('workspace_id', $page->workspace_id)->whereNull('deleted_at')->findOrFail((int) $data['metadata']['og_media_id']);
        }
        return response()->json(['data' => $service->saveDraft($page, $request->attributes->get('workspaceMember'), $data), 'message' => 'Draft autosaved.']);
    }

    /** Discards only the mutable Website Studio autosave draft. */
    public function discardDraft(Request $request, WebsitePage $page, WebsiteBuilderService $service): JsonResponse
    {
        $this->ensurePage($request, $page);
        $service->discardDraft($page);
        return response()->json(['message' => 'Autosave draft discarded.']);
    }

    /** Runs server-side publishing preflight against the current in-memory editor payload. */
    public function preflightPage(Request $request, WebsitePage $page, WebsiteBuilderService $service): JsonResponse
    {
        $this->ensurePage($request, $page);
        $data = $request->validate(['schema' => 'required|array', 'metadata' => 'nullable|array']);
        return response()->json(['data' => $service->preflightPage($page, $data['schema'], (array) ($data['metadata'] ?? []))]);
    }

    /** Creates an immutable staging version from the exact in-memory editor state after server preflight. */
    public function stagePage(Request $request, WebsitePage $page, WebsiteBuilderService $service): JsonResponse
    {
        $this->ensurePage($request, $page);
        $data = $request->validate(['schema' => 'required|array', 'metadata' => 'nullable|array']);
        $staged = $service->stagePage($page, $request->attributes->get('workspaceMember'), $data);
        return response()->json(['data' => $staged, 'message' => 'Page staged for review.']);
    }

    /** Creates one expiring shareable link for the current staged page version. */
    public function createPreviewToken(Request $request, WebsitePage $page, WebsiteBuilderService $service): JsonResponse
    {
        $this->ensurePage($request, $page);
        $data = $request->validate(['expires_hours' => 'nullable|integer|min:1|max:168']);
        return response()->json(['data' => $service->createPreviewToken($page, $request->attributes->get('workspaceMember'), (int) ($data['expires_hours'] ?? 72)), 'message' => 'Staging preview link created.'], 201);
    }

    /** Revokes one staging preview token owned by the current workspace. */
    public function revokePreviewToken(Request $request, WebsitePreviewToken $previewToken, WebsiteBuilderService $service): JsonResponse
    {
        abort_unless((int) $previewToken->workspace_id === (int) $request->attributes->get('workspace')->id, 404);
        $service->revokePreviewToken($previewToken);
        return response()->json(['message' => 'Preview link revoked.']);
    }

    /** Returns review comments attached to one page. */
    public function comments(Request $request, WebsitePage $page, WebsiteBuilderService $service): JsonResponse
    {
        $this->ensurePage($request, $page);
        return response()->json(['data' => $service->pageComments($page)]);
    }

    /** Creates one page- or section-level review comment. */
    public function storeComment(Request $request, WebsitePage $page, WebsiteBuilderService $service): JsonResponse
    {
        $this->ensurePage($request, $page);
        $data = $request->validate(['message' => 'required|string|max:5000', 'section_id' => 'nullable|string|max:120']);
        return response()->json(['data' => $service->createPageComment($page, $request->attributes->get('workspaceMember'), $data), 'message' => 'Review comment added.'], 201);
    }

    /** Resolves or reopens one Website Studio review comment. */
    public function updateComment(Request $request, WebsitePageComment $comment, WebsiteBuilderService $service): JsonResponse
    {
        abort_unless((int) $comment->workspace_id === (int) $request->attributes->get('workspace')->id, 404);
        $data = $request->validate(['status' => ['required', Rule::in(['open','resolved'])]]);
        return response()->json(['data' => $service->updatePageComment($comment, $request->attributes->get('workspaceMember'), $data['status']), 'message' => $data['status'] === 'resolved' ? 'Comment resolved.' : 'Comment reopened.']);
    }

    /** Publishes the current immutable page version. */
    public function publishPage(Request $request, WebsitePage $page, WebsiteBuilderService $service): JsonResponse
    {
        $this->ensurePage($request, $page);
        return response()->json(['data' => $service->publishPage($page, $request->attributes->get('workspaceMember')), 'message' => 'Page published.']);
    }

    /** Copies an old page revision into a new editable current version. */
    public function restoreVersion(Request $request, WebsitePage $page, WebsitePageVersion $version, WebsiteBuilderService $service): JsonResponse
    {
        $this->ensurePage($request, $page);
        return response()->json(['data' => $service->restoreVersion($page, $version, $request->attributes->get('workspaceMember')), 'message' => 'Version restored into a new draft.']);
    }

    /** Archives a page without deleting its immutable version history. */
    public function archivePage(Request $request, WebsitePage $page): JsonResponse
    {
        $this->ensurePage($request, $page);
        abort_if($page->is_home && $page->status === 'published', 422, 'Publish another home page before archiving the live home page.');
        $page->update(['status' => 'archived', 'navigation_visible' => false, 'updated_by_member_id' => $request->attributes->get('workspaceMember')->id]);
        return response()->json(['message' => 'Page archived.']);
    }

    /** Restores an archived page to draft status without publishing it. */
    public function restorePage(Request $request, WebsitePage $page): JsonResponse
    {
        $this->ensurePage($request, $page);
        abort_unless($page->status === 'archived', 422, 'Only archived pages can be restored.');
        $page->update(['status' => 'draft', 'updated_by_member_id' => $request->attributes->get('workspaceMember')->id]);
        return response()->json(['data' => $page->fresh(), 'message' => 'Page restored to draft.']);
    }

    /** Creates one reusable section from a current editor section. */
    public function storeReusableSection(Request $request, WebsiteBuilderService $service): JsonResponse
    {
        $site = $service->ensureSite($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'));
        $data = $request->validate(['name' => 'required|string|max:160', 'schema' => 'required|array', 'is_global' => 'sometimes|boolean']);
        return response()->json(['data' => $service->saveReusableSection($site, $request->attributes->get('workspaceMember'), $data), 'message' => 'Reusable section saved.'], 201);
    }

    /** Updates an existing reusable section. */
    public function updateReusableSection(Request $request, WebsiteReusableSection $section, WebsiteBuilderService $service): JsonResponse
    {
        $this->ensureSection($request, $section);
        $site = $service->ensureSite($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'));
        $data = $request->validate(['name' => 'required|string|max:160', 'schema' => 'required|array', 'is_global' => 'sometimes|boolean']);
        return response()->json(['data' => $service->saveReusableSection($site, $request->attributes->get('workspaceMember'), $data, $section), 'message' => 'Reusable section updated.']);
    }

    /** Deletes one reusable section without affecting pages that copied its schema. */
    public function destroyReusableSection(Request $request, WebsiteReusableSection $section): JsonResponse
    {
        $this->ensureSection($request, $section);
        $section->delete();
        return response()->json(['message' => 'Reusable section deleted.']);
    }

    /** Creates a public lead form owned by the workspace website. */
    public function storeForm(Request $request, WebsiteBuilderService $service): JsonResponse
    {
        $site = $service->ensureSite($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'));
        return response()->json(['data' => $service->saveForm($site, $request->attributes->get('workspaceMember'), $this->formData($request)), 'message' => 'Website form created.'], 201);
    }

    /** Updates a workspace website form. */
    public function updateForm(Request $request, WebsiteForm $form, WebsiteBuilderService $service): JsonResponse
    {
        $this->ensureForm($request, $form);
        $site = $service->ensureSite($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'));
        return response()->json(['data' => $service->saveForm($site, $request->attributes->get('workspaceMember'), $this->formData($request), $form), 'message' => 'Website form updated.']);
    }

    /** Archives a website form so it can no longer receive public submissions. */
    public function destroyForm(Request $request, WebsiteForm $form): JsonResponse
    {
        $this->ensureForm($request, $form);
        $form->update(['status' => 'archived']);
        return response()->json(['message' => 'Website form archived.']);
    }

    /** Returns website leads with safe DataGrid search, sorting, status and date-range filters. */
    public function submissions(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $grid = DataGridRequest::from($request, ['submitted_at','status','form'], ['status','submitted_at'], [['id' => 'submitted_at', 'desc' => true]]);
        $query = WebsiteFormSubmission::query()->where('workspace_id', $workspace->id)->with('form:id,name');
        if (($status = $grid->filter('status')) && is_string($status)) $query->where('status', $status);
        $range = $grid->dateRange('submitted_at');
        if ($range['from'] ?? null) $query->whereDate('submitted_at', '>=', $range['from']);
        if ($range['to'] ?? null) $query->whereDate('submitted_at', '<=', $range['to']);
        if ($grid->search !== '') {
            $term = '%'.str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $grid->search).'%';
            $query->where(function ($nested) use ($term) { $nested->where('status', 'like', $term)->orWhereHas('form', fn ($formQuery) => $formQuery->where('name', 'like', $term)); });
        }
        foreach ($grid->sorting as $sort) {
            if ($sort['id'] === 'form') $query->orderBy(WebsiteForm::select('name')->whereColumn('website_forms.id', 'website_form_submissions.website_form_id'), $sort['desc'] ? 'desc' : 'asc');
            else $query->orderBy($sort['id'], $sort['desc'] ? 'desc' : 'asc');
        }
        $total = (clone $query)->count();
        $rows = $query->forPage($grid->page, $grid->pageSize)->get()->map(fn ($submission) => [
            'id' => $submission->id,
            'uuid' => $submission->uuid,
            'form' => $submission->form?->name,
            'form_id' => $submission->website_form_id,
            'status' => $submission->status,
            'payload' => $submission->payload,
            'consent' => $submission->consent,
            'source_url' => $submission->source_url,
            'internal_note' => $submission->internal_note,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
        ]);
        return response()->json(['data' => $rows, 'meta' => $grid->meta($total)]);
    }

    /** Updates internal lead status or notes without mutating the submitted payload. */
    public function updateSubmission(Request $request, WebsiteFormSubmission $submission): JsonResponse
    {
        abort_unless((int) $submission->workspace_id === (int) $request->attributes->get('workspace')->id, 404);
        $data = $request->validate(['status' => ['sometimes', Rule::in(['new','contacted','qualified','closed','spam','archived'])], 'internal_note' => 'nullable|string|max:5000']);
        $submission->update($data);
        return response()->json(['data' => $submission->fresh(), 'message' => 'Lead updated.']);
    }

    /** Validates the shared create/update form contract. */
    private function formData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:160',
            'slug' => 'nullable|string|max:120',
            'website_page_id' => 'nullable|integer',
            'status' => ['sometimes', Rule::in(['active','inactive','archived'])],
            'fields' => 'required|array|min:1|max:30',
            'settings' => 'nullable|array',
            'success_message' => 'nullable|string|max:1000',
            'notification_emails' => 'nullable|array|max:20',
            'notification_emails.*' => 'email|max:190',
        ]);
    }

    /** Rejects cross-workspace page route-model access. */
    private function ensurePage(Request $request, WebsitePage $page): void { abort_unless((int) $page->workspace_id === (int) $request->attributes->get('workspace')->id, 404); }

    /** Rejects cross-workspace reusable-section route-model access. */
    private function ensureSection(Request $request, WebsiteReusableSection $section): void { abort_unless((int) $section->workspace_id === (int) $request->attributes->get('workspace')->id, 404); }

    /** Rejects cross-workspace website-form route-model access. */
    private function ensureForm(Request $request, WebsiteForm $form): void { abort_unless((int) $form->workspace_id === (int) $request->attributes->get('workspace')->id, 404); }
}
