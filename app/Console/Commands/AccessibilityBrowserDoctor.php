<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Verifies Block N accessibility source landmarks and cross-browser certification wiring without requiring a database. */
class AccessibilityBrowserDoctor extends Command
{
    protected $signature = 'workintel:accessibility-doctor {--json} {--require-build}';
    protected $description = 'Validate keyboard, semantic, reduced-motion, RTL and cross-browser certification prerequisites.';

    /** Run non-destructive accessibility/browser readiness checks and return a failing exit code when a contract is missing. */
    public function handle(): int
    {
        $checks = [];
        $failed = false;

        /** Register one deterministic source marker check in the doctor result. */
        $checkSource = function (string $name, string $relative, array $markers) use (&$checks, &$failed): void {
            $path = base_path($relative);
            $body = is_file($path) ? (string) file_get_contents($path) : '';
            $missing = array_values(array_filter($markers, fn (string $marker): bool => ! str_contains($body, $marker)));
            $ok = is_file($path) && $missing === [];
            $checks[$name] = [
                'ok' => $ok,
                'detail' => $ok ? "{$relative} accessibility contract present." : ($body === '' ? "Missing {$relative}." : 'Missing markers: '.implode(', ', $missing)),
            ];
            if (! $ok) $failed = true;
        };

        $checkSource('focus_management', 'resources/js/design-system/accessibility.ts', ['FOCUSABLE_SELECTOR', 'useFocusTrap', 'returnFocusRef']);
        $checkSource('shared_semantics', 'resources/js/design-system/index.tsx', ['aria-modal="true"', 'role="tablist"', 'aria-sort', 'role="progressbar"']);
        $checkSource('skip_links', 'resources/js/WorkforceApp.tsx', ['ui-skip-link', 'id="workintel-main"']);
        $checkSource('accessibility_css', 'resources/css/app.css', ['prefers-reduced-motion:reduce', '@media(pointer:coarse)', '@media(forced-colors:active)', ':focus-visible']);
        $checkSource('browser_inventory', 'tools/e2e-browser.mjs', ['findChromeExecutable', 'findEdgeExecutable', 'findFirefoxExecutable']);
        $checkSource('browser_matrix', 'tools/playwright.config.mjs', ['accessibilityProjects', 'firefox-desktop', 'reflow-200pct-equivalent', 'touch-mobile']);
        $checkSource('accessibility_journey', 'tests/e2e/accessibility-platform.spec.mjs', ['command palette traps keyboard focus', 'reduced motion', 'touch profile']);

        $packagePath = base_path('package.json');
        $package = is_file($packagePath) ? json_decode((string) file_get_contents($packagePath), true) : null;
        $scripts = ['test:e2e:accessibility', 'test:e2e:cross-browser', 'accessibility:audit'];
        $missingScripts = array_values(array_filter($scripts, fn (string $script): bool => empty($package['scripts'][$script])));
        $scriptOk = $missingScripts === [];
        $checks['npm_scripts'] = ['ok' => $scriptOk, 'detail' => $scriptOk ? 'Block N npm certification scripts registered.' : 'Missing scripts: '.implode(', ', $missingScripts)];
        if (! $scriptOk) $failed = true;

        $manifest = public_path('build/manifest.json');
        $buildOk = is_file($manifest);
        $checks['frontend_build'] = ['ok' => $buildOk, 'detail' => $buildOk ? 'Vite production manifest present.' : 'public/build/manifest.json is not present.'];
        if ($this->option('require-build') && ! $buildOk) $failed = true;

        $result = ['ok' => ! $failed, 'checks' => $checks];
        if ($this->option('json')) $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        else foreach ($checks as $name => $check) $this->line(sprintf('[%s] %s — %s', $check['ok'] ? 'PASS' : 'FAIL', $name, $check['detail']));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
