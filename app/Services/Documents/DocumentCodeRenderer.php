<?php

namespace App\Services\Documents;

use Symfony\Component\Process\Process;

/** Produces optional standards-compliant QR and Code128 SVG through a dependency-isolated adapter. */
class DocumentCodeRenderer
{
    /** Renders a QR SVG or a clear non-scannable fallback when the optional adapter is unavailable. */
    public function qr(string $value): string
    {
        return $this->render('qr', $value) ?? $this->fallback('QR', $value);
    }

    /** Renders a Code128 SVG or a clear non-scannable fallback when the optional adapter is unavailable. */
    public function barcode(string $value): string
    {
        return $this->render('barcode', $value) ?? $this->fallback('BARCODE', $value);
    }

    /** Reports whether the optional Python code-generation adapter can execute. */
    public function available(): bool
    {
        return $this->render('qr', 'WorkIntel') !== null && $this->render('barcode', 'WorkIntel') !== null;
    }

    /** Executes the bundled adapter and returns sanitized SVG output when successful. */
    private function render(string $format, string $value): ?string
    {
        $python = $this->pythonBinary();
        $script = base_path('tools/document-code-svg.py');
        if (! $python || ! is_file($script) || $value === '') return null;
        try {
            $encoded = rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
            $process = new Process([$python, $script, $format, $encoded]);
            $process->setTimeout(8)->run();
            if (! $process->isSuccessful()) return null;
            $svg = trim($process->getOutput());
            if (! str_contains($svg, '<svg') || preg_match('/<script|onload\s*=|javascript:/i', $svg)) return null;
            return $svg;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Finds a configured or commonly available Python interpreter without shell interpolation. */
    private function pythonBinary(): ?string
    {
        $configured = config('documents.code_python_binary');
        if (is_string($configured) && $configured !== '' && is_file($configured)) return $configured;
        foreach (PHP_OS_FAMILY === 'Windows' ? ['python.exe', 'py.exe'] : ['python3', '/usr/bin/python3', '/usr/local/bin/python3'] as $candidate) {
            if (str_contains($candidate, DIRECTORY_SEPARATOR) && is_file($candidate)) return $candidate;
            try {
                $process = new Process([$candidate, '--version']);
                $process->setTimeout(2)->run();
                if ($process->isSuccessful()) return $candidate;
            } catch (\Throwable) {
                // Try the next interpreter candidate.
            }
        }
        return null;
    }

    /** Returns an explicit text fallback that never pretends to be a scannable code. */
    private function fallback(string $label, string $value): string
    {
        $label = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<div class="document-code-fallback"><strong>'.$label.'</strong><span>'.$value.'</span><small>Code adapter unavailable</small></div>';
    }
}
