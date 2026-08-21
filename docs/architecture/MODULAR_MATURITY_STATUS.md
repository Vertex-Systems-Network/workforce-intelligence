# WorkIntel Modular Maturity Status

Updated: 2026-08-21

| Phase | Weight | Progress | Status |
|---|---:|---:|---|
| M0 — Roadmap & Acceptance Criteria | 5% | 100% | Complete |
| M1 — Full System Inventory & Module Map | 8% | 100% | Complete |
| M2 — WorkIntel Design System V1 | 12% | 100% | Complete — source ratchets plus hosted Linux/MySQL and Windows browser/build certification are green; target-workstation parity is tracked only under M12 |
| M3 — Application Shell & Information Architecture | 9% | 100% | Complete — responsive/browser certification is green; target-workstation parity is tracked only under M12 |
| M4 — Shared UX Systems V3 | 10% | 100% | Complete |
| M5 — Core Workforce Module Conversion | 10% | 100% | Complete |
| M6 — Business/Admin Module Conversion | 10% | 100% | Complete |
| M7 — Media DAM V3 | 8% | 100% | Complete — DB-backed flows, build/typecheck and browser certification are green |
| M8 — Website Studio V3 | 8% | 100% | Complete — DB-backed flows, build/typecheck and responsive/browser certification are green |
| M9 — Document Studio V6 | 7% | 100% | Complete — MySQL/PHPUnit, build/typecheck and browser certification are green |
| M10 — Chat & Collaboration V4 | 5% | 100% | Complete — DB-backed flow, build/typecheck and browser/accessibility certification are green |
| M11 — Role UX + Help + Onboarding | 4% | 100% | Complete — localized guidance, onboarding, RTL and browser contracts are certified |
| M12 — Accessibility, Performance & Final Certification | 4% | 95% | Hosted certification complete; only combined target Laragon Windows + MySQL workstation proof remains |

**Overall weighted modular maturity: 99.8%**

## Certification evidence — 2026-08-21

PR #4 (`Certification: add Windows cross-browser release gate`) landed on `main` as merge SHA `91fe105b6258c1441a6fa66097926b4830d59f79`.

- WorkIntel CI run `32500867687` completed successfully on Linux/MySQL with frontend tests, typecheck, production build, performance budget, fresh MySQL migration/seed, second seed pass, full PHPUnit, production/final doctors, full responsive E2E, accessibility E2E, MySQL smoke, route boot and scheduler boot all green.
- WorkIntel Windows Certification run `32500867908` completed successfully with installed Chrome, Microsoft Edge and Firefox detected; frontend tests, typecheck, production build, performance budget, SQLite migration/seed, second seed pass, full PHPUnit, production/final doctors, full responsive E2E, actual Chrome/Edge/Firefox accessibility certification, route boot and scheduler boot all green.
- The Windows PHPUnit proof is 315/315 tests passing with 3631 assertions.

This closes the previously deferred cross-phase automated build/DB/browser certification. The remaining M12 5% is intentionally reserved for one combined execution on the real Laragon Windows + MySQL workstation; hosted split-platform evidence must not be relabeled as that physical target proof.

M5 is functionally closed in the uploaded Core Workforce Max Closure baseline: Work Management, Time & Attendance, and People & HR use specialized module homes and shared WorkIntel UX contracts.

M6 MAX Batch brings the tenant-side Business/Admin surfaces onto the same foundation. Clients & Commerce, Finance & Payroll, Intelligence & Reports, and Administration now have specialized role-aware module homes. M6 workspace pages contain zero legacy `TableWrap` surfaces and zero browser-native `window.prompt()` flows. Payment, finance, payroll, reporting and enterprise form workflows were migrated to shared `DataGrid V3`, `FormDialog`, `SettingRow`, `ChoiceList` and related Design System contracts where applicable.

M6 is functionally closed at 100%: all audited tenant Business/Admin pages use the shared WorkIntel UX contracts with zero legacy `TableWrap` and zero browser-native prompt flows. Cross-phase browser/build certification is tracked under M12 rather than keeping a completed domain conversion artificially open.

M7 functional closure is implemented and certified: collections and restricted sharing, favorites/Recent/rights views, immutable metadata and binary history, stable-ID binary replacement, safe restore/download, focal-point-aware renditions, bulk DAM operations, resumable chunked uploads with browser resume state and scheduled abandoned-session cleanup, and copyright/license expiry governance.

M8 functional closure is implemented and certified: persistent autosave separated from immutable versions, staging-first publishing, expiring/revocable no-cache share previews, page/section review comments, linked global-component propagation into mutable drafts, allowlisted dynamic bindings, responsive breakpoint overrides, Media DAM-aware preflight and public/editor renderer parity.

M9 is complete. The final closure adds retry-safe idempotent large batches with stale-worker recovery, Media DAM-backed Brand Kit logos, per-page Page Master overrides, immutable generated workflow-policy snapshots, ordered review/approval/signature enforcement, and structural linked-resource deletion guards; automated MySQL/PHPUnit, build/typecheck and browser evidence is green.

M10 is complete. The closure adds persistent inbox triage (done/snooze/follow-up/read-all), an effective granular notification preference matrix, cursor-paged large-channel context, bounded bulk pin/bookmark cleanup, and permission-safe rich project/task/document cards; automated DB-backed, build/typecheck and browser/accessibility evidence is green.

M11 is complete with a permission-aware Help Center, role-specific Start Here checklists, persisted personal onboarding progress, contextual page guidance and role manuals without duplicating navigation or authorization. Automated localized/RTL/browser certification is green.

M12 now owns the only remaining release-certification delta. Hosted Linux/MySQL and Windows actual-browser automation is fully green. Final 100% requires `verify-laragon-release.cmd` to pass on the real target Laragon workstation using the configured MySQL database; see `docs/architecture/LARAGON_RELEASE_CERTIFICATION.md`.

Development closure after M12 Batch 1 removed the final 11 tenant workspace `TableWrap` surfaces by migrating Live Workforce, Devices, Field Workforce, Apps & Websites, Security Events and Audit Logs onto shared DataGrid V3. DataGrid adoption is 75 tenant feature surfaces and tenant `TableWrap` debt is locked at zero. The M2 inline-style ratchet is reduced from 545 to 445.
