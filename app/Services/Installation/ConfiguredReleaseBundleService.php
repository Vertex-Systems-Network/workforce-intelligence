<?php

namespace App\Services\Installation;

use App\Services\Releases\ReleaseCatalogService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PharData;
use RuntimeException;
use Throwable;

/** Builds temporary server-bound deployment bundles without mutating canonical published releases. */
class ConfiguredReleaseBundleService
{
    /** Initializes the service with the canonical release catalog dependency. */
    public function __construct(private readonly ReleaseCatalogService $catalog) {}

    /** Build a temporary deployment ZIP containing only the current WorkIntel server origin as runtime configuration. */
    public function build(string $slug, string $serverUrl): array
    {
        $release = $this->catalog->find($slug);
        if (! $release) {
            throw new RuntimeException('Release not found.');
        }

        $source = $this->catalog->absolutePath($release);
        if (! $source || ! is_file($source)) {
            throw new RuntimeException('Release file is not available on this server.');
        }

        $expectedHash = strtolower((string) ($release['sha256'] ?? ''));
        $actualHash = strtolower((string) hash_file('sha256', $source));
        if ($expectedHash === '' || ! hash_equals($expectedHash, $actualHash)) {
            throw new RuntimeException('Canonical release failed integrity verification.');
        }

        $origin = $this->normalizeOrigin($serverUrl);
        $directory = storage_path('app/private/installation-bundles');
        File::ensureDirectoryExists($directory, 0750, true);

        $canonicalName = (string) ($release['filename'] ?? basename($source));
        $stem = pathinfo($canonicalName, PATHINFO_FILENAME);
        $path = $directory.'/'.$stem.'-'.Str::lower(Str::random(16)).'.zip';
        $downloadName = $stem.'-server-bound.zip';

        if (! copy($source, $path)) {
            throw new RuntimeException('Could not stage the deployment bundle.');
        }

        try {
            $archive = new PharData($path);
            $configPath = ($release['kind'] ?? null) === 'extension'
                ? 'workintel-server.txt'
                : 'desktop-agent/workintel-server.txt';
            $archive->addFromString($configPath, $origin."\n");
            unset($archive);
        } catch (Throwable $error) {
            File::delete($path);
            throw new RuntimeException('Could not bind the deployment package to this WorkIntel server.', 0, $error);
        }

        return [
            'path' => $path,
            'name' => $downloadName,
            'mime' => 'application/zip',
            'server_url' => $origin,
            'sha256' => (string) hash_file('sha256', $path),
            'release' => $release,
        ];
    }

    /** Normalize a trusted request URL to scheme + authority only. */
    private function normalizeOrigin(string $value): string
    {
        $parts = parse_url(trim($value));
        if (! is_array($parts)) {
            throw new RuntimeException('WorkIntel server URL is invalid.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('WorkIntel server URL must be an absolute http:// or https:// origin without credentials.');
        }

        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            $host = '['.$host.']';
        }

        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }
}
