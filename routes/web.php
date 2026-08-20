<?php

use App\Http\Controllers\Api\V1\SystemHealthController;
use App\Models\WebsitePage;
use App\Models\WebsiteSite;
use App\Models\Workspace;
use App\Models\WorkspaceDomain;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/health/live', [SystemHealthController::class, 'live']);
Route::get('/health/ready', [SystemHealthController::class, 'ready']);

Broadcast::routes(['middleware' => ['auth:sanctum']]);

/**
 * Serves the React shell and resolves SEO-safe public website context for both
 * /site/{workspace} URLs and verified custom website domains. Migration-safe
 * guards keep fresh installations bootable before Website Studio tables exist.
 */
Route::get('/{path?}', function (Request $request) {
    $publicWebsiteHost = null;
    $publicWebsiteMeta = null;
    $host = strtolower(trim($request->getHost()));

    try {
        if (Schema::hasTable('workspace_domains') && Schema::hasTable('website_sites') && Schema::hasTable('website_pages')) {
            $site = null;
            $pagePath = '';

            if ($host !== '' && Schema::hasColumn('workspace_domains', 'purpose')) {
                $domain = WorkspaceDomain::query()
                    ->where('hostname', $host)
                    ->where('purpose', 'website')
                    ->whereIn('status', ['verified', 'active'])
                    ->whereHas('websiteSite', fn ($query) => $query->where('status', 'published'))
                    ->first();
                if ($domain) {
                    $candidate = $domain->websiteSite()->with('workspace')->first();
                    if ($candidate?->workspace && app(EntitlementService::class)->allows($candidate->workspace, 'feature.custom_domains')) {
                        $site = $candidate;
                        $publicWebsiteHost = $domain->hostname;
                        $pagePath = trim((string) $request->path(), '/');
                    }
                }
            }

            if (! $site && str_starts_with(trim((string) $request->path(), '/'), 'site/')) {
                $parts = explode('/', trim((string) $request->path(), '/'));
                $slug = (string) ($parts[1] ?? '');
                $pagePath = implode('/', array_slice($parts, 2));
                $workspace = $slug !== '' ? Workspace::query()->where('slug', $slug)->first() : null;
                $site = $workspace ? WebsiteSite::query()->where('workspace_id', $workspace->id)->where('status', 'published')->with('workspace')->first() : null;
            }

            if ($site) {
                $language = strtolower((string) $request->query('lang', $site->default_language ?: 'en'));
                $pages = WebsitePage::query()->where('website_site_id', $site->id)->where('status', 'published')->whereNotNull('published_version');
                $page = $pagePath === '' || $pagePath === 'home'
                    ? (clone $pages)->where('language', $language)->where('is_home', true)->with('ogMedia:id,uuid')->first()
                    : (clone $pages)->where('language', $language)->where('slug', trim($pagePath, '/'))->with('ogMedia:id,uuid')->first();
                if (! $page && $language !== $site->default_language) {
                    $page = $pagePath === '' || $pagePath === 'home'
                        ? (clone $pages)->where('language', $site->default_language)->where('is_home', true)->with('ogMedia:id,uuid')->first()
                        : (clone $pages)->where('language', $site->default_language)->where('slug', trim($pagePath, '/'))->with('ogMedia:id,uuid')->first();
                }
                if ($page) {
                    $defaults = (array) ($site->seo_defaults ?? []);
                    $suffix = trim((string) ($defaults['title_suffix'] ?? ''));
                    $title = trim((string) ($page->seo_title ?: $page->title));
                    if (! $page->seo_title && $suffix !== '' && ! str_contains(strtolower($title), strtolower($suffix))) $title .= ' · '.$suffix;
                    $publicWebsiteMeta = [
                        'title' => $title,
                        'description' => (string) ($page->seo_description ?: ($defaults['description'] ?? '')),
                        'language' => $page->language,
                        'direction' => in_array($page->language, ['ar', 'ur'], true) ? 'rtl' : 'ltr',
                        'canonical' => $request->fullUrlWithoutQuery(['lang']),
                        'og_image' => $page->ogMedia?->uuid ? url('/api/v1/media/public/'.$page->ogMedia->uuid) : null,
                    ];
                }
            }
        }
    } catch (\Throwable $exception) {
        report($exception);
    }

    return view('app', ['publicWebsiteHost' => $publicWebsiteHost, 'publicWebsiteMeta' => $publicWebsiteMeta]);
})->where('path', '^(?!api(?:/|$)|sanctum(?:/|$)|broadcasting(?:/|$)|health(?:/|$)|up$).*$');
