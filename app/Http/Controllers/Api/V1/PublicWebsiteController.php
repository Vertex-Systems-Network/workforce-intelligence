<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebsiteForm;
use App\Models\WebsiteSite;
use App\Models\Workspace;
use App\Models\WorkspaceDomain;
use App\Services\WebsiteBuilderService;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Serves published workspace websites and public lead submissions without exposing editor data. */
class PublicWebsiteController extends Controller
{
    /** Resolves one published website by workspace slug and requested public path. */
    public function show(Request $request, Workspace $workspace, WebsiteBuilderService $service): JsonResponse
    {
        $site = WebsiteSite::query()->where('workspace_id', $workspace->id)->firstOrFail();
        return response()->json($service->publicPayload($site, (string) $request->query('path', ''), $request->query('lang')));
    }

    /** Resolves one published website by a verified/active custom-domain hostname. */
    public function resolveHost(Request $request, WebsiteBuilderService $service, EntitlementService $entitlements): JsonResponse
    {
        $host = strtolower(trim((string) $request->query('host')));
        abort_if($host === '' || strlen($host) > 255, 404);
        $domain = WorkspaceDomain::query()->where('hostname', $host)->where('purpose', 'website')->whereIn('status', ['verified','active'])->firstOrFail();
        $site = WebsiteSite::query()->where('workspace_id', $domain->workspace_id)->where('custom_domain_id', $domain->id)->with('workspace')->firstOrFail();
        abort_unless($site->workspace && $entitlements->allows($site->workspace, 'feature.custom_domains'), 404);
        return response()->json($service->publicPayload($site, (string) $request->query('path', ''), $request->query('lang')));
    }

    /** Resolves one revocable Website Studio staging preview by opaque share token. */
    public function preview(string $token, WebsiteBuilderService $service): JsonResponse
    {
        return response()->json($service->previewPayload($token))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Captures a rate-limited public lead for one active form in the requested workspace website. */
    public function submit(Request $request, Workspace $workspace, string $formUuid, WebsiteBuilderService $service): JsonResponse
    {
        $form = WebsiteForm::query()->where('workspace_id', $workspace->id)->where('uuid', $formUuid)->with('site')->firstOrFail();
        $submission = $service->submitForm($form, $request);
        return response()->json(['message' => $form->success_message ?: 'Thanks. Your message has been received.', 'submission' => ['uuid' => $submission->uuid]], 201);
    }
}
