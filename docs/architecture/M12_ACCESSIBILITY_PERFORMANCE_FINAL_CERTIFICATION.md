# M12 — Accessibility, Performance & Final Certification

M12 is the release-evidence layer for the modular WorkIntel program. It does not replace module logic; it makes accessibility, performance, runtime and packaging quality measurable and mandatory in CI/release workflows.

## Implemented in MAX Batch 1

- **Measurable source budgets** for JS/TS source size/count, CSS size and unusually large non-i18n feature files.
- **Production build budgets** for total JavaScript, gzip JavaScript, largest JavaScript chunk, CSS, asset count and source-map prohibition.
- **Stable Vite vendor chunking** for React, charts, rich-text/editor and drag/drop/layout dependencies so heavyweight libraries can be cached independently from application code.
- **Final Laravel doctor** (`workintel:final-certification`) that validates route-count bounds, WorkIntel scheduler-count bounds, current production schema landmarks, PHP/PDO runtime, production build presence, absence of `public/hot`, and production debug policy.
- **Production certification catalog refresh** for Media DAM V3, Website Studio V3, Document Studio V6 and Chat V4 tables added during M7–M10.
- **Expanded browser accessibility contract** for duplicate IDs, visible form-control accessible names and a visible main landmark, on top of existing skip-link, focus trap, keyboard menu/tab, reduced-motion, RTL, reflow and touch coverage.
- **CI hardening**: built-asset performance audit and final Laravel doctor are mandatory after production build/schema setup.
- **Release-script hardening**: source M12 audit, source performance budget, M12 PHPUnit contracts, built performance budget and final Laravel doctor are mandatory in both non-destructive release and clean-install verification.
- **Dependency-free M12 source contract** so final certification wiring is tested even before target Node/PHP dependencies are installed.

## Remaining target certification

M12 stays below 100% until a target environment with full PHP extensions/PDO, installed frontend dependencies and Playwright browser engines completes:

1. `npm install` / deterministic lockfile install when lockfile exists.
2. Full TypeScript check and Vite production build.
3. Built-asset performance budgets.
4. `migrate:fresh --seed` and complete PHPUnit suite.
5. `workintel:production-doctor --require-build` and `workintel:final-certification --require-build`.
6. Full browser certification including keyboard, focus, mobile/touch, RTL, reflow, reduced-motion and structural accessibility checks.
7. Final clean-install package verification and target release evidence.
