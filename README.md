# WorkIntel Workforce Intelligence

WorkIntel is a single Laravel 13 + React/TypeScript workforce operations platform. Laravel serves the React SPA and API from the same application; there is no second frontend project and no fixed hostname in the source.

## Development progress & phase status

The table below is the repository-level roadmap view. `Progress` represents accepted phase completion/evidence gates, not code volume. A green source/CI state is not the same as `PRODUCTION_VERIFIED`; external signing, release and real-target evidence remain separate gates where required.

| Phase | Module / scope | Status | Progress | Start date | End date | Current gate / note |
|---|---|---|---|---|---|---|
| M0 | Roadmap & Acceptance Criteria | Complete | `██████████` 100% | 2026-08-20* | 2026-08-21* | Historical roadmap foundation complete |
| M1 | Full System Inventory & Module Map | Complete | `██████████` 100% | 2026-08-20* | 2026-08-21* | Inventory/classification accepted |
| M2 | WorkIntel Design System V1 | Complete | `██████████` 100% | 2026-08-20* | 2026-08-21* | Source ratchets + build/browser certification complete |
| M3 | Application Shell & Information Architecture | Complete | `██████████` 100% | 2026-08-20* | 2026-08-21* | Responsive/browser certification complete |
| M4 | Shared UX Systems V3 | Complete | `██████████` 100% | 2026-08-20* | 2026-08-21* | Shared UX contracts complete |
| M5 | Core Workforce Module Conversion | Complete | `██████████` 100% | 2026-08-20* | 2026-08-21* | Core workforce conversion closed |
| M6 | Business/Admin Module Conversion | Complete | `██████████` 100% | 2026-08-20* | 2026-08-21* | Business/admin conversion closed |
| M7 | Media DAM V3 | Complete | `██████████` 100% | 2026-08-20* | 2026-08-21* | DB-backed, build/typecheck and browser evidence complete |
| M8 | Website Studio V3 | Complete | `██████████` 100% | 2026-08-20* | 2026-08-21* | Publishing/editor/browser contracts complete |
| M9 | Document Studio V6 | Complete | `██████████` 100% | 2026-08-20* | 2026-08-21* | MySQL/PHPUnit/build/browser evidence complete |
| M10 | Chat & Collaboration V4 | Complete | `██████████` 100% | 2026-08-20* | 2026-08-21* | DB/build/browser/accessibility evidence complete |
| M11 | Role UX + Help + Onboarding | Complete | `██████████` 100% | 2026-08-20* | 2026-08-21* | Localized guidance/onboarding/RTL contracts complete |
| M12 | Accessibility, Performance & Final Certification | Complete — active-scope closure | `██████████` 100% | 2026-08-21 | 2026-08-22 | Hosted + Windows certification accepted; withdrawn Laragon gate is not represented as passed |
| M13 | Agent Lifecycle Reliability — Batches 1–6 | Complete | `██████████` 100% | 2026-08-22 | 2026-08-24 | Managed update, deterministic packaging, immutability, transactional publication, browser version authority and runtime-bound deployment accepted |
| M14 | Production Release Trust & Real-Target Readiness | **VERIFYING / PARTIALLY COMPLETE** | `██████░░░░` **60%** | 2026-08-31 | **Active** | Source trust lane is implemented/hardened; independent review, actual Windows/macOS trust evidence, trusted release publication and real-target/restore evidence remain open |

\* The canonical M0–M12 maturity record stores per-phase completion state but not precise per-phase start/end timestamps. M0–M11 therefore use the repository's initial implementation/certification evidence window instead of inventing unsupported day-level precision. M12, M13 and M14 dates are tied to explicit repository/PR authority and closure records.

**Current roadmap state:** M0–M13 are accepted complete. M14 is the active authorized phase and must not be labeled `DONE` or `PRODUCTION_VERIFIED` until its independent-review and external evidence gates are actually satisfied. See `docs/architecture/MODULAR_MATURITY_STATUS.md`, `docs/architecture/M13_AGENT_LIFECYCLE_RELIABILITY.md`, `docs/architecture/M14_RELEASE_TRUST_READINESS.md`, and `docs/status/AI_CHECKPOINT.md`.

## Clean project structure

```text
workforce/
├── app/                  Laravel application code
├── bootstrap/            Laravel bootstrap
├── browser-extension/    WorkIntel browser tracker sources
├── config/               Application configuration
├── database/             Migrations, seeders, SQLite test/local placeholder
├── deploy/               Deployment helpers
├── desktop-agent/        WorkIntel desktop agent sources/installers
├── docs/                 Current project documentation
├── lang/                 Backend translations
├── public/               Web document root
├── resources/            React/TypeScript/CSS/Blade sources
├── routes/               Web, API and console routes
├── storage/              Runtime storage directories
├── tests/                PHPUnit and frontend contract tests
├── tools/                Release/integrity audit tools
├── artisan
├── composer.json
├── package.json
└── vite.config.ts
```

The release package intentionally excludes `vendor/`, `node_modules/`, `.env`, compiled `public/build`, runtime caches and logs. They are installation/runtime artifacts, not source files.

## Requirements

Use these minimum runtime requirements before installation:

- PHP 8.3 or newer.
- Composer 2.
- Node.js `^20.19.0` or `>=22.12.0`.
- npm.
- PHP extensions: `openssl`, `pdo`, `mbstring`, `dom`, `xmlwriter`, `fileinfo`, `json`.
- `pdo_sqlite` for the default local/test installation and PHPUnit.
- `pdo_mysql` when using MySQL/MariaDB.
- HTTPS in production.

Run the dependency/runtime doctor before installing:

```bat
php workintel-doctor.php
```

## Fresh zero installation

The included `.env.example` is intentionally domain-agnostic and defaults to a local SQLite database so a clean development/test install does not require MySQL first.

```bat
cd /d D:\laragon\www\workforce
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm install
npm test
npm run typecheck
npm run build
```

`migrate:fresh` is destructive. Use it only for a new/disposable database.

The repository includes an empty `database/database.sqlite` placeholder for the default local configuration.

### MySQL / Laragon instead of SQLite

For a MySQL-backed Laragon install, change only the database section of `.env` before running migrations:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=workforce
DB_USERNAME=root
DB_PASSWORD=
```

Use a database user/password appropriate for the server. Do not hardcode a deployment hostname in source code; set `APP_URL` in `.env`.

## Strict clean-install verification

For a disposable test database, run:

```bat
verify-clean-install.cmd
```

The script requires typing `RESET` before it can call `migrate:fresh`. It then checks source integrity/documentation, migration integrity, Composer dependencies, a fresh migration+seed, a second seed pass for idempotency, migration status, unit tests, the full PHPUnit suite, npm install, frontend contract tests, TypeScript, the production build, routes and scheduler.

For an existing database, use the non-destructive release gate:

```bat
verify-release.cmd
```

That script uses normal `php artisan migrate --force`; it never calls `migrate:fresh`.

## Seeded local accounts

The development seeders create role-specific sample users. The primary local credentials are:

```text
Owner:           owner@acme.test      / password
Admin:           admin@acme.test      / password
HR:              hr@acme.test         / password
Manager:         manager@acme.test    / password
Team Lead:       teamlead@acme.test   / password
Payroll Manager: payroll@acme.test    / password
Employee:        employee@acme.test   / password
```

Demo accounts are development/test data and should be disabled or removed for production deployments.

## Frontend development

Install dependencies once:

```bat
npm install
```

For Laragon build-watch development:

```bat
npm run dev
```

For direct Vite HMR when your environment is configured for it:

```bat
npm run dev:hmr
```

Production frontend verification is always:

```bat
npm test
npm run typecheck
npm run build
```

## Database upgrade rule

Existing installations must use additive migrations:

```bat
php artisan workintel:migration-doctor
php artisan migrate --force
```

Never run `migrate:fresh` against a database containing data that must be preserved.

Migration filenames are intentionally treated as immutable database-history identifiers. Historical migration filenames are not renamed during source cleanup because Laravel records those exact names in the `migrations` table.

## Current platform areas

The consolidated application contains the current WorkIntel platform modules, including:

- People, organization, custom roles and scoped access.
- Projects, Task Engine V2, time tracking and timesheets.
- Attendance, leave, scheduling and approvals.
- Desktop/browser activity and screenshot tracking.
- Screenshot external-storage adapters and health/retry queue.
- HRIS, performance, payroll and compliance.
- Expenses, procurement/job costing and field workforce.
- Reports, workforce intelligence and automation/integrations.
- Enterprise identity/governance and commercial platform tooling.
- Global Settings Center and multilingual/RTL localization.
- Document & Template Studio.
- Downloads & Installation Center.
- Live Chat & Collaboration with Chat V2.4 enterprise guests, retention, legal hold, eDiscovery, DLP and audited moderation, plus V2.3 governed channels and V2.2 professional messaging.
- SaaS Seller / Buyer Commerce.
- Workspace Module Manager.

## Drag/drop policy

Use the library that matches the interaction:

- GridStack: dashboard/widget grids that need drag, resize and persisted layouts.
- dnd-kit: sortable/Kanban/list/container drag/drop.
- Do not add ad-hoc native HTML5 `draggable`/`dataTransfer` flows for application interaction surfaces.

## Realtime chat

Chat works with its polling fallback without a WebSocket server. To enable Laravel Reverb push realtime, run:

```bat
setup-realtime-chat.cmd
```

Then configure Reverb environment values and run the Reverb process under an appropriate production process supervisor.

## Source quality gates

Dependency-free/release audits:

```bat
php tools\release-smoke.php
php tools\chat-stabilization-smoke.php
php tools\chat-professional-messaging-smoke.php
php tools\chat-workspace-collaboration-smoke.php
node tools\audit-source-integrity.mjs
php tools\audit-php-documentation.php
node tools\audit-js-documentation.mjs
php tools\audit-migrations.php
```

With Composer dependencies installed:

```bat
php tools\run-unit-smoke.php
php tools\audit-seeders.php
php tools\migration-recovery-smoke.php
```

All named first-party PHP classes/functions/methods and named JS/TS classes/interfaces/functions/components are required to have PHPDoc/JSDoc documentation. The audit commands enforce that contract.

## Production checklist

Before real traffic:

```env
APP_ENV=production
APP_DEBUG=false
WORKINTEL_SHOW_DEMO_ACCOUNTS=false
```

Also configure a strong `APP_KEY`, HTTPS `APP_URL`, production database credentials, mail/queue/cache services as required, platform operator emails, backups, scheduler/workers, and any enabled external storage/payment/SSO providers. See `docs/PRODUCTION_CHECKLIST.md`.

## Important security boundaries

- Frontend visibility is not authorization; backend workspace permissions and scopes are authoritative.
- Workspace module OFF state blocks the corresponding backend module while preserving data.
- Secrets/tokens are never intended to be returned after initial issuance and provider credentials are encrypted at rest where applicable.
- Seller Console is restricted by the Platform Operator boundary and is not automatically available to every workspace Owner.
- External outbound/custom provider URLs are protected by server-side URL/SSRF validation.
- Historical financial/document records preserve their stored snapshots when workspace defaults later change.

## Chat V2.1 — Professional Collaboration Stabilization

The collaboration module now excludes the signed-in member from conversation creation on both the API and client layers, rejects explicit self-DMs, reuses canonical direct-message pairs, filters inactive members, and preserves presence privacy. The chat UI uses a stable desktop three-pane layout, tablet details drawer, mobile one-panel navigation, scroll-safe unread handling, debounced typing presence, and RTL-safe logical layout rules. See `docs/features/CHAT_STABILIZATION_V2_1.md`.


## Chat V2.4 — Enterprise Collaboration

- Single-conversation guest/client/vendor invitations with hard expiry and a restrictive external collaborator role.
- Conversation retention, legal hold, private eDiscovery JSON/CSV exports, DLP monitor/quarantine/block policies and audited moderation.
- Hourly `workintel:chat-enterprise-maintenance` safely expires external access, applies retention outside legal hold, and removes expired private exports.
- `workintel:chat-v2.4-doctor` validates enterprise chat schema, permissions, routes, UI and maintenance wiring.
- Detailed design: `docs/features/CHAT_ENTERPRISE_COLLABORATION_V2_4.md`.

## Chat V2.3 — Workspace Collaboration

Chat now supports discoverable public channels, private and announcement channels, channel roles (`owner`, `admin`, `moderator`, `member`, `read_only`), channel lock/archive governance, per-conversation notification delivery, pinned resources, built-in system/automation bots, workspace action cards, and slash commands including `/task`, `/assign`, `/poll`, `/status`, and `/help`. Task, approval, and incident actions reuse their existing backend modules and permission boundaries rather than creating parallel records. See `docs/features/CHAT_WORKSPACE_COLLABORATION_V2_3.md`.


## Chat V2.5 — Performance & Production Certification

The collaboration stack now includes cursor-based older/newer synchronization, server idempotency keys, independent delivered/read cursors, a persistent text outbox for reconnect recovery, multi-tab synchronization, browser rendering containment, batched payload-state queries, request throttles and bounded attachment safety. See `docs/features/CHAT_PERFORMANCE_CERTIFICATION_V2_5.md`.

After upgrade run `php artisan workintel:chat-v2.5-doctor`. The strict release scripts run the complete Chat V2.1–V2.5 regression chain before the full test/build gates.

## UI and runtime stabilization

The current release includes a shared professional UI layer for single-select menus, file inputs, transient toast feedback, page motion and per-user page customization. The Overview dashboard uses GridStack only while **Edit layout** is enabled and stores widget visibility/layout server-side for each user and workspace. Essential widgets are enabled by default; optional analytics remain available through **Manage widgets**.

Clean Composer installation first runs `tools/prepare-runtime.php` and then builds Laravel's package manifest through `tools/discover-packages.php`. This prevents missing archive directories and terminal-rendering dependencies from breaking `post-autoload-dump`. For a strict environment check run `php tools/runtime-preflight.php`; the release verification scripts run this automatically before migrations and PHPUnit.

See `docs/features/UI_RUNTIME_STABILIZATION.md` for the implementation and verification contract.

## UI Foundation V3 — Block C DataGrid V2

The shared WorkIntel DataGrid now uses TanStack React Table v8 for sorting, filtering, pagination, column visibility, row selection and controlled server-side state. Grid search/filter/sort/page preferences and named views are persisted per user and workspace. The first migration wave covers People, Clients, Projects and Tasks. Large endpoints can use `dataGridQueryParams()` with the backend `App\Support\DataGridRequest` whitelist helper. See `docs/features/DATA_GRID_V2.md`.

## Data Lifecycle + Media Library

Recoverable Trash/Restore/Permanent Delete policies, private Media Library with folders/tags/checksum dedupe/usage tracking, profile photo crop + Media Picker, and destination-shaped loading states are documented in `docs/features/DATA_LIFECYCLE_MEDIA.md`.

## Block E — Localization & Navigation V2
WorkIntel now uses parity-checked EN/TR/RU/UR/AR core catalogs, per-user language overrides, immutable role-aware navigation, a consolidated Scheduling hub, shared human-readable labels and RTL-safe overlay/table/form behavior. See `docs/features/LOCALIZATION_NAVIGATION_V2.md`.

## Localization Full Page Copy E.1

Block E.1 extends Localization & Navigation V2 into legacy deep-module page copy for EN/TR/RU/UR/AR. New code continues to use canonical localization keys; a guarded legacy bridge translates only registered static product copy and deliberately leaves business data, form values, code/technical examples and rich-content regions untouched. See `docs/features/LOCALIZATION_FULL_PAGE_COPY_E1.md`.

## Commerce V2 — Seller Platform & Client Payments

Commerce V2 separates WorkIntel platform subscription commerce from each workspace's own client billing. Platform operators use the dedicated `/seller` shell to manage customers, plans, capability entitlements, add-ons, taxes, coupons, platform payment providers, transactions, refunds and dunning. Workspace owners use **Client Payments** to configure their own encrypted gateway credentials, control Client Portal Pay Now methods, reconcile payments and automate recurring client invoices. Seller plan capability changes survive routine catalog synchronization and can hide/disable Client Payments features for plans that do not include them. Remote providers use a save → connection test → enable lifecycle so first-time credential setup cannot deadlock. See `docs/features/COMMERCE_V2.md`.

## Block G — Document Studio V4

Document Studio V4 adds a paged three-pane visual designer, nested dynamic blocks, formulas/conditions/repeats, Media Library assets, reusable components, live server preview, Chromium/Edge Unicode PDF output with a recorded legacy fallback, immutable version comparison, comments, review/approval/locking, hash-token sharing and external electronic signatures. Generated documents retain an encrypted render-context snapshot so final signed output can be reproduced independently of later source-data changes. See `docs/features/DOCUMENT_STUDIO_V4.md`.

## Block H — Website & Portal Builder

Eligible workspaces can now build a versioned public company website from Website Studio. The module supports Home/About/Contact/Services/Portfolio/Buy/Sell/Careers/Blog/custom pages, dnd-kit section ordering, reusable sections, responsive preview, Media Library assets, lead forms/submissions, SEO/OpenGraph metadata, multilingual/RTL public rendering, publishing/version history and verified custom-domain assignment. Website Studio reuses WorkIntel permissions, modules, plan entitlements, localization, Media Library and DataGrid rather than creating parallel subsystems. See `docs/features/WEBSITE_PORTAL_BUILDER_BLOCK_H.md`.

## Block I — Production Certification & Final Platform QA

Block I adds the final release-certification layer: current-schema production readiness, Playwright desktop/tablet/mobile journeys, dropdown/table overflow regressions, RTL navigation switching, Chat/Seller browser checks, lock-aware CI, full clean-install seed idempotency and a `workintel:production-doctor` command. See `docs/features/PRODUCTION_CERTIFICATION_BLOCK_I.md`.

## Real runtime certification

Run `powershell -ExecutionPolicy Bypass -File tools\\run-runtime-closure.ps1 -Mode Release` for a non-destructive certification of an existing installation. For a disposable clean database use `-Mode Clean -ConfirmReset`. The Block J runner writes a timestamped diagnostic log under `storage/logs/runtime-closure/` and stops on the first failing gate.

## Block K — Production Operations & Disaster Recovery

The platform-operator Seller surface includes an **Operations & Recovery** center for backup policy, verified restore points, retention, system health and short-lived CLI restore authorization. Use `php artisan workintel:operations-doctor --json` to validate backup tooling and storage. Destructive restore is intentionally CLI-only.
## Observability & Audit Operations

Platform operators can use the Seller **Observability** tab to review privacy-safe runtime events, slow requests/queries, queue failures, webhook/storage/payment health, alert incidents and subsystem heartbeats. Use `php artisan workintel:observability-doctor --json` for non-destructive readiness diagnostics.


## Block M — Security Production Hardening
Seller Platform now includes a secret-safe Security posture view. Production security adds configurable CSP/COOP/CORP headers, strengthened password rules, named authentication/upload/public-form throttles, byte-level upload MIME inspection, optional ClamAV enforcement with quarantine, successor-based API-key rotation, and `php artisan workintel:security-doctor --json`. See `docs/features/SECURITY_PRODUCTION_HARDENING.md`.

## Block N — Accessibility & Cross-Browser Certification

Block N adds shared keyboard focus trapping/return, skip links and main landmarks, semantic tabs/menus/DataGrid/progress controls, reduced-motion/forced-colors/coarse-pointer CSS, narrow-screen reflow hardening and a Playwright accessibility profile covering Chrome/Chromium, Edge when installed, Firefox, 200%-equivalent reflow, tablet and mobile touch. Run `npm run accessibility:audit`, `php artisan workintel:accessibility-doctor --json`, and `npm run test:e2e:accessibility`. Set `WORKINTEL_REQUIRE_CROSS_BROWSER=1` on the final Windows release workstation to require actual installed Chrome, Edge and Firefox before release verification. See `docs/features/ACCESSIBILITY_BROWSER_CERTIFICATION.md`.

### Block N Laragon source sync
Before re-running the full Laragon suite after applying the final Block N correction, run `verify-block-n-final-sync.cmd`. It fails immediately if the active project still contains one of the four stale files seen in the 2026-08-14 Laragon report.

## Block O — Studio & Unified UX

Website Studio now includes bounded undo/redo, responsive visibility/layout controls, preview zoom, searchable sections and stronger typography/theme editing. Document Studio V4 adds editor history, keyboard shortcuts, preflight diagnostics and preview zoom. Media Library adds progress-aware multi-upload, storage/limit diagnostics and a shared **Media Library / Upload** chooser used across branding, imports, tasks, expenses, HRIS and Chat. Grid/table switching and table presentation now use shared UI primitives. See `docs/features/STUDIO_UNIFIED_UX_BLOCK_O.md`.