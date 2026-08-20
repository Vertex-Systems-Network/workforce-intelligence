# M1 — Full System Inventory & Module Map

## Baseline

This inventory is generated from the latest Block P runtime/media/UI hotfix baseline. It does not migrate runtime navigation yet; it locks ownership and migration decisions first.

## Current inventory

- Workspace shell page IDs: **40**
- Page source files under `resources/js/pages`: **54**
- Owner navigation destinations: **39**
- Legacy switchable module keys: **26**
- Target workspace modules: **11**
- Special product surfaces: **4**
- Permission slugs: **149** across **39** permission groups
- Laravel route source files: **17**
- Static route declarations: **676**
- Legacy `routes/api.php` declarations: **337**
- UI/page/shared TS/TSX files inventoried: **84**
- Raw `<button>` occurrences in inventoried UI files: **144**
- Raw `<input>` occurrences: **33**
- Raw `<select>`: **2**; raw `<textarea>`: **2**; raw `<table>`: **1**
- Raw `<img>` occurrences: **12**
- Inline React style objects: **1300**

## Locked target architecture

1. **Home & Command Center** — Personalized workspace start, live status, notifications, recent work and global commands.
1. **Work Management** — Projects, tasks, approvals and automations used to plan and execute work.
1. **Collaboration** — Chat, channels, direct conversations and contextual collaboration.
1. **Time & Attendance** — Attendance, timesheets, leave, shifts and scheduling.
1. **People & HR** — People directory, HRIS, organization and performance lifecycle.
1. **Workforce Operations** — Activity tracking, apps/sites, screenshots, devices and field workforce operations.
1. **Clients & Commerce** — Clients, client portal commerce, payments and customer-facing business workflows.
1. **Content Studio** — Media Library, Website Studio and Document Studio.
1. **Finance & Payroll** — Expenses, procurement, job costing, payroll, compliance and billing.
1. **Intelligence & Reports** — Workforce intelligence, analytics, saved reports and exports.
1. **Administration** — Workspace modules, roles, settings, enterprise controls, integrations and lifecycle administration.

Special surfaces are deliberately outside normal tenant module navigation: **Account & Support**, **Platform Console**, **Public Experience**, and **Authentication**.

## High-impact consolidation decisions

- **Shifts → Scheduling:** the standalone Shifts destination is a MERGE target; Scheduling is canonical.
- **Legacy Scheduling.tsx → remove:** SchedulingHub is the canonical implementation.
- **Platform → separate Platform Console:** operator/seller controls must not remain mixed into tenant workspace administration.
- **Finance → Expenses & Procurement:** current mixed finance page is renamed for user clarity; Payroll remains a distinct area inside Finance & Payroll.
- **Client Commerce → Client Billing & Payments:** makes intent explicit.
- **Timesheets & Timer → Timesheets:** timer remains a workflow/action, not a competing navigation concept.
- **Website Studio + Document Studio + Media Library → Content Studio:** one product family, three dedicated tools.
- **Activity, Apps & Websites, Screenshots, Devices, Field Workforce → Workforce Operations:** monitoring/agent evidence is separated from HR and attendance.

## Main maturity risks discovered

1. **Route ownership concentration:** `routes/api.php` still owns 337 static declarations. This must be split by module during M5/M6 without breaking URLs.
2. **UI implementation drift:** raw controls and 1300 inline style objects remain across feature code. M2 must move feature pages onto the WorkIntel Design System rather than continue page-specific styling.
3. **Navigation vs capability mismatch:** 26 legacy switchable module keys and 39 permission groups collapse into 11 user-facing modules. Backend capability keys should stay granular while the user-facing information architecture becomes simpler.
4. **Duplicate scheduling surface:** a non-canonical Scheduling.tsx remains beside SchedulingHub. It is explicitly marked for removal after route/reference verification.
5. **Tenant/operator boundary:** Platform functionality remains a workspace page today; the target architecture moves it into its own app shell.

## M1 acceptance status

- Screen/page ownership: **complete**
- Navigation role inventory: **complete**
- Legacy module → target module map: **complete**
- Permission → target module map: **complete**
- Route file ownership: **complete**
- Runtime route ownership: generated separately in `docs/architecture/M1_ROUTE_INVENTORY.json`
- UI/source file inventory: **complete**
- KEEP / MERGE / RENAME / MOVE / REMOVE decisions: **complete for all current shell destinations and known non-sidebar pages**

No runtime menu migration is performed in M1. M2 creates the Design System; M3 consumes this architecture contract to rebuild the application shell.
