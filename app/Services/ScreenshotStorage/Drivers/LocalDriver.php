<?php
namespace App\Services\ScreenshotStorage\Drivers;

use App\Services\ScreenshotStorage\Contracts\StorageProviderDriver;
use Illuminate\Support\Facades\Storage;

/** Provides local driver behavior within the WorkIntel application. */ class LocalDriver implements StorageProviderDriver
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly string $root = 'screenshots') {}
    /** Handles the path operation for the current WorkIntel workflow. */ private function path(string $key): string { return trim($this->root, '/').'/'.ltrim($key, '/'); }
    /** Handles the put operation for the current WorkIntel workflow. */ public function put(string $key, string $contents, string $mimeType): array { $path=$this->path($key); if(!Storage::disk('local')->put($path,$contents)) throw new \RuntimeException('Local storage write failed.'); return ['key'=>$path,'id'=>null]; }
    /** Returns get data required by the current workflow. */ public function get(string $key, ?string $objectId = null): string { $path=$this->path($key); if(!Storage::disk('local')->exists($path)) throw new \RuntimeException('Local object not found.'); return Storage::disk('local')->get($path); }
    /** Removes delete data from the requested resource. */ public function delete(string $key, ?string $objectId = null): void { Storage::disk('local')->delete($this->path($key)); }
}
