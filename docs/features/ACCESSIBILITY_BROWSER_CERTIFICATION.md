# Block N — Accessibility & Cross-Browser Certification

Block N hardens shared WorkIntel surfaces for keyboard, assistive technology, reduced-motion, RTL, reflow and touch usage. It also expands browser certification beyond the historic single Chromium-family path.

## Shared accessibility contracts

The shared toolkit now provides focus trapping and focus return for modal surfaces, named modal/drawer/popover/menu controls, WAI-ARIA tab keyboard behavior, progressbar semantics, DataGrid sort/pagination status semantics, visible focus indicators and skip-to-content links. Authenticated Workspace, Seller Platform, Client Portal, public Website and Secure Document Sign surfaces expose explicit main landmarks.

`resources/js/design-system/accessibility.ts` is the canonical keyboard-focus helper. Modal-like custom surfaces should use `useFocusTrap` instead of introducing page-specific key listeners. Non-modal menus/popovers should preserve normal Tab order and return focus on Escape.

## Motion, contrast and touch

`resources/css/app.css` contains the Block N baseline:

- `prefers-reduced-motion: reduce` removes non-essential animation and transition timing.
- `forced-colors: active` retains visible focus and status treatments in high-contrast modes.
- coarse-pointer targets expand common buttons, icon buttons, menu items, tabs, switches and navigation actions.
- narrow layouts keep portal overlays inside the viewport and collapse the workforce sidebar on initial mobile load.
- Settings Center converts its desktop side navigation into a horizontally scrollable narrow-screen navigation strip.

## 200% zoom / reflow

The Playwright project `reflow-200pct-equivalent` uses a 640 CSS-pixel viewport, equivalent to viewing a 1280px desktop at approximately 200% zoom. The E2E suite checks viewport-wide overflow and critical operability at that width. Real workstation manual zoom remains useful for browser-specific text rendering differences.

## Browser matrix

Normal certification remains three responsive Chromium-family projects. Block N adds the accessibility profile:

- Chrome desktop when Google Chrome is installed; otherwise Playwright Chromium.
- Microsoft Edge desktop when Edge is installed.
- Playwright Firefox desktop engine.
- 640px reflow project.
- touch tablet.
- touch mobile.

Run:

```bash
npm run test:e2e:accessibility
```

For a Windows release workstation that must confirm actual installed Chrome, Edge and Firefox are present before running the matrix:

```bat
set WORKINTEL_REQUIRE_CROSS_BROWSER=1
verify-release.cmd
```

or directly:

```bash
npm run test:e2e:cross-browser
```

The browser doctor can be run separately:

```bash
node tools/e2e-browser-doctor.mjs --require-all
```

Playwright's Firefox project uses the Playwright Firefox build because automation protocol support is tied to that browser build. The system-browser doctor still confirms the workstation has the user-facing Firefox installation expected for final manual parity checks.

## Automated gates

Dependency-free source audit:

```bash
npm run accessibility:audit
php tools/accessibility-browser-smoke.php
```

Laravel doctor:

```bash
php artisan workintel:accessibility-doctor --json
```

When `public/build/manifest.json` must also be certified:

```bash
php artisan workintel:accessibility-doctor --json --require-build
```

The Block N PHPUnit contract is `AccessibilityBrowserCertificationContractTest` and the E2E browser journey is `tests/e2e/accessibility-platform.spec.mjs`.

## Scope and certification language

These gates are WCAG-oriented engineering checks, not a legal declaration of WCAG conformance. Full conformance still requires real-browser execution, assistive-technology/manual review and review of user-authored website/document content. WorkIntel cannot guarantee that tenant-authored colors, images, HTML or uploaded documents remain accessible after users customize them.
