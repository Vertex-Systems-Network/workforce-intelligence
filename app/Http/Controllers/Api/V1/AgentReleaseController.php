<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Releases\ReleaseCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Serves the current platform-specific desktop-agent release to authenticated devices. */
class AgentReleaseController extends Controller
{
    /** Returns metadata for the current authenticated device's stable agent release. */
    public function current(Request $request, ReleaseCatalogService $catalog): JsonResponse
    {
        [$release] = $this->resolveRelease($request, $catalog, false);

        return response()->json([
            'release' => [
                'slug' => $release['slug'],
                'version' => $release['version'],
                'filename' => $release['filename'] ?? null,
                'sha256' => $release['sha256'],
                'size_bytes' => $release['size_bytes'] ?? null,
                'platform' => $release['platform'] ?? null,
                'download_path' => '/api/v1/agent/release/download',
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /** Streams the verified stable agent package for the authenticated device platform. */
    public function download(Request $request, ReleaseCatalogService $catalog): BinaryFileResponse
    {
        [$release, $path] = $this->resolveRelease($request, $catalog, true);

        return response()->download($path, $release['filename'] ?? basename($path), [
            'Content-Type' => $release['mime_type'] ?? 'application/zip',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Release-SHA256' => $release['sha256'],
            'X-WorkIntel-Version' => $release['version'],
            'ETag' => '"'.$release['sha256'].'"',
        ]);
    }

    /** Resolves the only release slug authorized for the authenticated device platform. */
    private function resolveRelease(Request $request, ReleaseCatalogService $catalog, bool $verifyBinary): array
    {
        /** @var Device|null $device */
        $device = $request->attributes->get('device');
        abort_unless($device && $device->status === 'active', 401, 'Authenticated device is required.');

        $slug = match ($device->platform) {
            'windows' => 'agent-windows-x64',
            'macos' => 'agent-macos',
            'linux' => 'agent-linux',
            default => null,
        };
        abort_unless($slug, 422, 'No managed agent release exists for this device platform.');

        $release = $catalog->find($slug);
        abort_unless($release, 503, 'The managed agent release is not published on this server.');
        abort_unless(is_string($release['version'] ?? null) && $release['version'] !== '', 503, 'The managed agent release version is invalid.');
        abort_unless(is_string($release['sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/i', $release['sha256']) === 1, 503, 'The managed agent release checksum is invalid.');

        $path = $catalog->absolutePath($release);
        abort_unless($path && is_file($path), 503, 'The managed agent release file is not available on this server.');

        if ($verifyBinary) {
            $actualHash = hash_file('sha256', $path);
            abort_unless(is_string($actualHash) && hash_equals(strtolower($release['sha256']), strtolower($actualHash)), 503, 'The managed agent release failed integrity verification.');
        }

        return [$release, $path];
    }
}
