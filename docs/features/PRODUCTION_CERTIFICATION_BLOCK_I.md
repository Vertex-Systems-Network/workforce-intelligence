# Block I — Production Certification & Final Platform QA

Block I is the release-certification layer for WorkIntel. It does not add another business module; it verifies that the existing workforce, collaboration, commerce, document, website and UI foundations can be installed, built and exercised as one production application.

## Certification layers

1. **Source gates** — source integrity, PHPDoc/JSDoc, migration/seeder safety and every historical module smoke.
2. **Runtime gates** — Composer package discovery, runtime preflight, migrations, seed idempotency, Laravel routes/scheduler and the production doctor.
3. **Automated tests** — targeted contracts plus the complete PHPUnit and frontend Node test suites.
4. **Frontend build** — dependency install, TypeScript semantic check and Vite production build.
5. **Browser journeys** — Playwright against the built Laravel application using desktop, tablet and mobile viewports.
6. **Archive verification** — the distributed ZIP is re-extracted into an empty directory and the dependency-free gates are repeated.

## Browser certification

`npm run test:e2e:public` is non-destructive and verifies the public health endpoint, workspace login shell and dedicated seller login surface.

`npm run test:e2e:full` additionally uses a seeded or explicitly configured account and verifies:

- workspace dropdown stays open and anchored while scrolling;
- table action menus render in a body portal without table clipping;
- repeated language switching does not duplicate navigation;
- Arabic RTL remains viewport-safe;
- high-use DataGrid and Chat destinations load without page exceptions;
- a platform operator can open the dedicated Seller Platform.

Full mode defaults to `owner@acme.test` / `password`, which is appropriate only for the disposable seeded certification database. Override with `WORKINTEL_E2E_EMAIL` and `WORKINTEL_E2E_PASSWORD` when needed.

The runner automatically detects Chrome, Edge or Chromium. Set `WORKINTEL_E2E_BROWSER_EXECUTABLE` when the browser lives in a custom location. If no system browser is available, install Playwright Chromium with `npx playwright install chromium`.

## Production doctor

Run:

```bat
php artisan workintel:production-doctor --json --require-build
```

It verifies PHP runtime extensions, a PDO driver, APP_KEY, production debug policy, current critical routes, current schema landmarks, frontend certification scripts and the Vite build manifest.

The schema inventory includes the latest Media/Trash, Commerce V2, Document Studio V4 and Website Builder tables so readiness cannot report healthy while a recent product block is missing.

## Release commands

Existing database, non-destructive verification:

```bat
verify-release.cmd
```

Disposable clean database certification:

```bat
verify-clean-install.cmd
```

The clean command intentionally requires typing `RESET` because it executes `migrate:fresh`.

## CI

GitHub CI is lock-aware: it uses `npm ci` when `package-lock.json` exists and `npm install` otherwise. It installs Playwright Chromium, performs a fresh seeded SQLite run, executes full PHPUnit and browser certification, then performs a second MySQL migration/seed smoke against MySQL 8.4.

A production release is certified only when every required gate passes in the target CI/runtime environment. Environment-blocked gates must be reported as blocked rather than converted into false passes.
