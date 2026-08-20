<?php
namespace App\Services\ScreenshotStorage\Contracts;

/** Defines the storage provider driver contract used by WorkIntel. */ interface StorageProviderDriver
{
    /** @return array{key:string,id:?string} */
    /** Handles the put operation for the current WorkIntel workflow. */ public function put(string $key, string $contents, string $mimeType): array;
    /** Returns get data required by the current workflow. */ public function get(string $key, ?string $objectId = null): string;
    /** Removes delete data from the requested resource. */ public function delete(string $key, ?string $objectId = null): void;
}
