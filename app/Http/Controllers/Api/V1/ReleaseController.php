<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Releases\ReleaseCatalogService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Provides release controller behavior within the WorkIntel application. */ class ReleaseController extends Controller
{
    /** Returns the requested resource collection. */ public function index(ReleaseCatalogService $catalog): JsonResponse
    {
        return response()->json(['data' => collect($catalog->all())->map(function ($release) use ($catalog) {
            $path = $catalog->absolutePath($release);
            unset($release['file']);
            $release['available'] = (bool) ($path && is_file($path));
            $release['download_url'] = url('/api/v1/releases/'.$release['slug'].'/download');
            return $release;
        })->values()]);
    }

    /** Handles the download operation for the current WorkIntel workflow. */ public function download(string $slug, ReleaseCatalogService $catalog): BinaryFileResponse
    {
        $release = $catalog->find($slug);
        abort_unless($release, 404);
        $path = $catalog->absolutePath($release);
        abort_unless($path && is_file($path), 404, 'Release file is not available on this server.');
        return response()->download($path, $release['filename'] ?? basename($path), [
            'Content-Type' => $release['mime_type'] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'X-Release-SHA256' => $release['sha256'] ?? '',
            'X-WorkIntel-Version' => $release['version'] ?? '',
            'ETag' => isset($release['sha256']) ? '"'.$release['sha256'].'"' : '',
        ]);
    }
}
