<?php

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;

/** Inspects uploaded content using server-side MIME detection and an optional malware scanner. */
class UploadSecurityService
{
    /** MIME types that must never be accepted as ordinary user-uploaded content. */
    private const BLOCKED_MIME = [
        'application/x-dosexec', 'application/x-executable', 'application/x-msdownload',
        'application/x-sh', 'application/x-csh', 'application/x-httpd-php',
        'text/x-php', 'text/x-shellscript', 'text/html', 'application/xhtml+xml',
    ];

    /** Extensions whose content type must match a narrow image/document allowlist. */
    private const IMAGE_MIME = [
        'jpg' => ['image/jpeg', 'image/pjpeg'], 'jpeg' => ['image/jpeg', 'image/pjpeg'], 'png' => ['image/png', 'image/x-png'],
        'gif' => ['image/gif'], 'webp' => ['image/webp'], 'avif' => ['image/avif'], 'bmp' => ['image/bmp', 'image/x-ms-bmp'],
    ];

    /** Inspect one upload before it is moved into durable storage. */
    public function inspect(UploadedFile $file): array
    {
        $path = $this->readableUploadPath($file);

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: ''));
        $detectedMime = $this->detectMime($path);
        abort_if(in_array($detectedMime, self::BLOCKED_MIME, true), 422, 'The uploaded content type is not allowed.');

        if (isset(self::IMAGE_MIME[$extension])) {
            abort_unless(in_array($detectedMime, self::IMAGE_MIME[$extension], true), 422, 'The uploaded image content does not match its file extension.');
            abort_unless(@getimagesize($path) !== false, 422, 'The uploaded image could not be decoded safely.');
        }

        $scan = $this->scan($path);
        return ['mime' => $detectedMime ?: 'application/octet-stream', 'scan' => $scan, 'path' => $path];
    }

    /** Resolve the readable PHP upload path without requiring realpath(), which can return false on valid Windows temporary files. */
    private function readableUploadPath(UploadedFile $file): string
    {
        $candidates = [$file->getPathname(), $file->getRealPath()];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate) && is_readable($candidate)) return $candidate;
        }

        abort(422, 'The upload could not be inspected. The PHP temporary upload file is missing or unreadable.');
    }

    /** Detect the MIME type from file bytes instead of trusting the browser-provided Content-Type header. */
    private function detectMime(string $path): string
    {
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = strtolower((string) ($finfo->file($path) ?: ''));
            if ($mime !== '') return $mime;
        }
        if (function_exists('mime_content_type')) {
            $mime = strtolower((string) (@mime_content_type($path) ?: ''));
            if ($mime !== '') return $mime;
        }
        $image = @getimagesize($path);
        if (is_array($image) && ! empty($image['mime'])) return strtolower((string) $image['mime']);
        return 'application/octet-stream';
    }

    /** Run the configured ClamAV-compatible scanner without interpolating shell input. */
    private function scan(string $path): array
    {
        $driver = strtolower((string) config('workintel_security.uploads.malware_driver', 'none'));
        $required = (bool) config('workintel_security.uploads.malware_required', false);
        if ($driver === 'none') {
            abort_if($required, 503, 'Malware scanning is required but no scanner is configured.');
            return ['driver' => 'none', 'status' => 'not_configured'];
        }
        abort_unless($driver === 'clamav', 500, 'Unsupported malware scanner configuration.');

        $binary = trim((string) config('workintel_security.uploads.clamav_binary', 'clamscan'));
        try {
            $process = new Process([$binary, '--no-summary', '--infected', $path]);
            $process->setTimeout(max(2, (int) config('workintel_security.uploads.malware_timeout_seconds', 20)));
            $process->run();
            $exit = $process->getExitCode();
            if ($exit === 1) return ['driver' => 'clamav', 'status' => 'infected'];
            if ($exit === 0) return ['driver' => 'clamav', 'status' => 'clean'];
            if ($required) abort(503, 'Malware scanner is unavailable.');
            return ['driver' => 'clamav', 'status' => 'unavailable'];
        } catch (\Throwable $exception) {
            if ($required) abort(503, 'Malware scanner is unavailable.');
            report($exception);
            return ['driver' => 'clamav', 'status' => 'unavailable'];
        }
    }
}
