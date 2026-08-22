# Code Quality and Accessibility Gates

This document defines the repository-level quality contract for WorkIntel. It separates deterministic source checks, browser certification, and external accessibility analysis so that no tool is presented as stronger evidence than it actually provides.

## Local quality commands

Use the lightweight source-quality gate while developing:

```bash
npm run quality
```

It runs PHP/JavaScript documentation checks, frontend source contracts, dead-source/design-system/module audits, TypeScript typechecking, the accessibility source audit, and the source performance budget.

Before release, use:

```bash
npm run quality:full
```

That adds the production Vite build and post-build performance budget. Browser certification remains a separate release gate because it starts real browsers and runtime services.

For changed PHP files, Laravel Pint is the formatting/style authority:

```bash
vendor/bin/pint --test path/to/ChangedFile.php
```

The pull-request code-quality workflow automatically applies Pint only to changed PHP files so legacy code is not silently reformatted in unrelated changes.

## Automated code-quality tools

- **TypeScript (`tsc --noEmit`)**: compile-time type correctness.
- **Repository source audits**: design-system boundaries, module contracts, documentation, dead source, debug/temp residue, architecture and performance budgets.
- **Laravel Pint**: PHP formatting/style validation on changed PHP source.
- **GitHub CodeQL `security-and-quality`**: JavaScript/TypeScript static security and quality analysis.
- **PHPUnit + frontend Node tests**: behavior and contract regression coverage.
- **Playwright**: responsive, authenticated, keyboard, RTL, reduced-motion, reflow and cross-browser journeys during final certification.

## W3C / WCAG scope

The deterministic accessibility checks are **WCAG-oriented**, not a claim of formal W3C certification. They enforce source and browser contracts for semantics, accessible names, keyboard focus, contrast tokens, responsive reflow, target sizing, RTL, reduced motion and related failure classes.

Automated checks cannot prove complete accessibility. Manual keyboard/screen-reader review and independent tools remain appropriate for release acceptance.

## Real WAVE integration

`tools/wave-accessibility-audit.mjs` integrates with the official WebAIM hosted WAVE API. The hosted API requires a publicly reachable page and an API key; it cannot scan a Laragon `.test`, localhost, or other private-only URL.

Set the credentials/target only when a public deployment exists:

```powershell
$env:WAVE_API_KEY='your-key'
$env:WORKINTEL_WAVE_URL='https://public-workintel.example.com/'
npm run accessibility:wave
```

The adapter defaults to WAVE report type 1, a 1280px viewport, a 500ms evaluation delay, and strict thresholds of zero WAVE errors and zero contrast errors. Alerts are printed for review but are not automatically treated as failures.

Optional environment controls:

```text
WAVE_REPORT_TYPE=1..4
WAVE_VIEWPORT_WIDTH=320..3840
WAVE_EVAL_DELAY=0..10000
WAVE_MAX_ERRORS=0+
WAVE_MAX_CONTRAST_ERRORS=0+
```

The API key is sent only to the official WAVE request endpoint and is never printed by the script. `npm run quality` intentionally does **not** spend WAVE API credits or pretend that an external scan ran; use `npm run accessibility:wave` explicitly when a public target is available.

## Release order

1. Complete implementation and source cleanup.
2. Run local/source quality (`npm run quality`, Pint for changed PHP).
3. Build and run production quality (`npm run quality:full`).
4. Run the final browser/accessibility certification matrix.
5. Run WAVE against the public release candidate when credentials and a reachable URL are available.
6. Merge only after the required repository statuses are green.
