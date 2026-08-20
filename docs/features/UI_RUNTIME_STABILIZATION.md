# UI & Runtime Stabilization

This release hardens the application installation path and standardizes common user-interface behavior without changing tenant business data.

## Runtime installation

Composer now prepares Laravel writable directories before package discovery. Clean ZIP archives retain `.gitignore` placeholders for framework cache, view, session, private-storage and log directories. Package manifest generation is performed directly through Laravel's `PackageManifest`, so Composer installation does not depend on Termwind/DOM rendering merely to discover providers.

Use `php tools/runtime-preflight.php` before migration or PHPUnit certification. The preflight checks the PHP extensions and PDO driver needed by the application/test suite and exits with a concise remediation message instead of allowing a later opaque failure.

## Form controls

The shared UI toolkit owns single-select rendering through an accessible React listbox/portal with a hidden form value for named controls, avoiding the operating-system dropdown surface. Multi-selects remain native list controls because native multi-selection semantics are safer for the existing API forms. File inputs use a shared browse/filename control. Date/time fields continue to use the shared React date picker.

## User page customization

Each user can persist page customization independently for each workspace. Supported preferences include content width, interface/table density, motion level, sticky header and description visibility. The Overview page also stores dashboard widget visibility and GridStack coordinates in the same per-user preference record. Workspace-wide settings are not modified.

## Dashboard

The Overview dashboard uses GridStack for drag/resize only while **Edit layout** is active. Essential operational widgets are enabled by default. Secondary diagnostics/analytics are available in **Manage widgets** and default to hidden. Layout, widget visibility and reset behavior are persisted server-side per user/workspace instead of localStorage.

## Feedback and motion

API failures use a global styled toast with status-specific tone, automatic expiry and a dismiss button. Mutating API responses with a message produce success feedback. Modal, drawer, popover and page-entry motion is standardized and respects both `prefers-reduced-motion` and the user's page-level motion preference.

## Verification

Run `verify-release.cmd` for a non-destructive existing-database verification or `verify-clean-install.cmd` on a disposable database. Both scripts include source integrity, documentation, runtime preflight, migrations, seed checks, targeted UI/runtime tests, the full PHPUnit suite, `npm test`, TypeScript typecheck and the Vite production build.
