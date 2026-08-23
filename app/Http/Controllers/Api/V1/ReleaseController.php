<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Installation\ConfiguredReleaseBundleService;
use App\Services\Releases\ReleaseCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Provides release controller behavior within the WorkIntel application. */ class ReleaseController extends Controller
{
    /** Returns the requested resource collection. */
    public function index(ReleaseCatalogService $catalog): JsonResponse
    {
        return response()->json(['data' => collect($catalog->all())->map(function ($release) use ($catalog) {
            $path = $catalog->absolutePath($release);
            unset($release['file']);
            $release['available'] = (bool) ($path && is_file($path));
            $release['download_url'] = url('/api/v1/releases/'.$release['slug'].'/download');

            return $release;
        })->values()]);
    }

    /** Download a temporary server-bound deployment bundle while leaving the canonical release bytes untouched. */
    public function download(Request $request, string $slug, ReleaseCatalogService $catalog, ConfiguredReleaseBundleService $bundles): BinaryFileResponse
    {
        abort_unless($catalog->find($slug), 404, 'Release not found.');
        try {
            $bundle = $bundles->build($slug, $request->getSchemeAndHttpHost());
        } catch (RuntimeException $error) {
            report($error);
            abort(503, 'The server-bound release package could not be prepared.');
        }

        /** @var array<string,mixed> $release */
        $release = $bundle['release'];

        return response()->download($bundle['path'], $bundle['name'], [
            'Content-Type' => $bundle['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Deployment-SHA256' => $bundle['sha256'],
            'X-Canonical-Release-SHA256' => $release['sha256'] ?? '',
            'X-WorkIntel-Version' => $release['version'] ?? '',
            'X-WorkIntel-Configured-Server' => $bundle['server_url'],
        ])->deleteFileAfterSend(true);
    }
}
