<?php

namespace App\Services\Releases;

use Illuminate\Support\Str;

/** Provides release catalog service behavior within the WorkIntel application. */ class ReleaseCatalogService
{
    /** Handles the all operation for the current WorkIntel workflow. */ public function all(): array
    {
        $path = storage_path('app/releases/manifest.json');
        if (! is_file($path)) return [];
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded['releases'] ?? null) ? $decoded['releases'] : [];
    }

    /** Returns find data required by the current workflow. */ public function find(string $slug): ?array
    {
        return collect($this->all())->first(fn ($release) => ($release['slug'] ?? null) === $slug);
    }

    /** Handles the absolute path operation for the current WorkIntel workflow. */ public function absolutePath(array $release): ?string
    {
        $relative = ltrim((string) ($release['file'] ?? ''), '/\\');
        if ($relative === '' || Str::contains($relative, ['..', "\0"])) return null;
        $root = realpath(storage_path('app/releases'));
        $path = realpath(storage_path('app/releases/'.$relative));
        if (! $root || ! $path || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) return null;
        return $path;
    }
}
