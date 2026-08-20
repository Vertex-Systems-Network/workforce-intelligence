<?php

namespace App\Services\Documents;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/** Renders Document Studio output through Chromium when available with a legacy PDF safety fallback. */
class DocumentPdfRenderer
{
    /** Initializes the renderer with the HTML/legacy rendering engine. */
    public function __construct(private readonly DocumentTemplateRenderer $renderer) {}

    /** Returns PDF bytes plus driver metadata for audit and generated-document records. */
    public function render(DocumentTemplate $template, array $context): array
    {
        $driver = strtolower((string) config('documents.pdf_driver', 'auto'));
        if (in_array($driver, ['auto', 'chromium'], true)) {
            $browser = $this->chromiumBinary();
            if ($browser) {
                $result = $this->renderWithChromium($browser, $this->renderer->renderHtml($template, $context, true));
                if ($result !== null) return ['bytes' => $result, 'driver' => 'chromium', 'unicode_capable' => true];
                if ($driver === 'chromium') throw new \RuntimeException('Chromium document rendering failed. Check DOCUMENT_CHROMIUM_BINARY and writable storage.');
            } elseif ($driver === 'chromium') {
                throw new \RuntimeException('Chromium document rendering is configured but no supported browser binary was found.');
            }
        }

        return ['bytes' => $this->renderer->renderPdf($template, $context), 'driver' => 'legacy', 'unicode_capable' => false];
    }

    /** Reports the active browser binary used by the Unicode-capable renderer. */
    public function browserBinary(): ?string
    {
        return $this->chromiumBinary();
    }

    /** Prints one self-contained HTML document to PDF with a temporary isolated browser profile. */
    private function renderWithChromium(string $browser, string $html): ?string
    {
        $root = storage_path('app/private/document-render');
        File::ensureDirectoryExists($root, 0775, true);
        $id = Str::uuid()->toString();
        $htmlPath = $root.DIRECTORY_SEPARATOR.$id.'.html';
        $pdfPath = $root.DIRECTORY_SEPARATOR.$id.'.pdf';
        $profilePath = $root.DIRECTORY_SEPARATOR.$id.'-profile';
        File::put($htmlPath, $html);
        File::ensureDirectoryExists($profilePath, 0775, true);

        try {
            $uri = 'file:///'.ltrim(str_replace('\\', '/', realpath($htmlPath) ?: $htmlPath), '/');
            if (PHP_OS_FAMILY !== 'Windows') $uri = 'file://'.str_replace('\\', '/', realpath($htmlPath) ?: $htmlPath);
            $arguments = [
                $browser,
                '--headless',
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--no-pdf-header-footer',
                '--run-all-compositor-stages-before-draw',
                '--virtual-time-budget=1200',
                '--user-data-dir='.$profilePath,
                '--print-to-pdf='.$pdfPath,
                $uri,
            ];
            if (PHP_OS_FAMILY !== 'Windows' && function_exists('posix_geteuid') && posix_geteuid() === 0) {
                array_splice($arguments, 3, 0, ['--no-sandbox']);
            }
            $process = new Process($arguments);
            // Browser child processes can inherit output pipes; disabling output prevents a failed render from keeping PHP blocked after timeout.
            $process->disableOutput();
            $process->setTimeout(max(10, (int) config('documents.render_timeout_seconds', 35)))->run();
            if (! $process->isSuccessful() || ! is_file($pdfPath)) return null;
            $bytes = File::get($pdfPath);
            return str_starts_with($bytes, '%PDF-') ? $bytes : null;
        } catch (\Throwable) {
            return null;
        } finally {
            @unlink($htmlPath);
            @unlink($pdfPath);
            File::deleteDirectory($profilePath);
        }
    }

    /** Finds Chrome, Chromium or Edge on supported Windows, macOS and Linux installations. */
    private function chromiumBinary(): ?string
    {
        $configured = config('documents.chromium_binary');
        if (is_string($configured) && $configured !== '' && is_file($configured)) return $configured;
        $candidates = PHP_OS_FAMILY === 'Windows' ? [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        ] : (PHP_OS_FAMILY === 'Darwin' ? [
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
            '/Applications/Chromium.app/Contents/MacOS/Chromium',
        ] : [
            '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/microsoft-edge',
        ]);
        foreach ($candidates as $candidate) if (is_file($candidate) && is_executable($candidate)) return $candidate;
        return null;
    }
}
