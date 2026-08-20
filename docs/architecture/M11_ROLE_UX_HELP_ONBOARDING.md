# M11 — Role UX + Help + Onboarding

M11 makes WorkIntel self-documenting without creating a second authorization or navigation system. Recommendations are derived from the existing page/module catalog and filtered through effective page access plus module navigation visibility.

## Implemented

- Global Help Center available from the shell and via `F1` / `Ctrl+/`, separate from Cmd+K command search.
- Contextual **This page** guidance with purpose, first steps, risk notes and permission-aware related destinations.
- Role-aware **Start Here** checklists for Owner/Admin, Manager/Team Lead, HR, Payroll Manager and Employee/custom-role inference.
- Custom role guide inference uses effective permission signals; it never grants access.
- Start Here is visible on Home and on module homes when that role has relevant tasks in the module.
- Checklist progress is personal per-user/per-workspace and persists through the existing `user_page_preferences` store under `role-help-v1`.
- Checklist completion never changes business workflow state, workspace configuration or authorization.
- Help Center search only returns destinations the current member can access and that are visible through enabled modules.
- Role handbook view explains intended outcomes and only lists currently accessible work areas.
- Five source role manuals are shipped under `docs/manuals/`.
- Mobile shell retains icon access to Find and Help rather than hiding both contextual actions.
- M11 audit and frontend/PHPUnit/Feature contracts are wired into test/typecheck/build and Windows release gates.

## Remaining closure

- Target Laragon DB-backed role-preference Feature test.
- Installed-node TypeScript/Vite build certification.
- Playwright keyboard/mobile/RTL/browser certification.
- Final localization pass for new M11 English source copy and final empty-state/help polish during M12 certification.

## MAX functional closure

- Localizes M11 Help Center chrome, Start Here controls, first-run guidance and module-help prompts across the five core UI locales: English, Turkish, Russian, Urdu and Arabic.
- Routes role-guide headlines, summaries, handbook outcomes and contextual copy through the existing known-text/page-copy localization bridge instead of inventing a second translation runtime.
- Adds a non-blocking first-run invitation. Starting it records only personal onboarding state and opens the Start Here tab; dismissing it never changes workspace data or permissions.
- Adds one shared `EmptyState.contextualHelp` recovery contract. Module homes plus selected legacy Scheduling/Automation blank states can open the current page's permission-aware Help Center without page-specific shell wiring.
- Adds RTL directional icon mirroring and right-aligned help/checklist layouts while preserving the existing Drawer portal/focus-trap contract.
- Adds reduced-motion/mobile first-run styles and a Playwright full-mode contract for F1, Help Center dialog accessibility and RTL viewport containment.
- M11 is functionally closed at 95%. The remaining 5% is target Laragon DB-backed preference testing, installed-node build/typecheck and Playwright execution under the real browser matrix. Final cross-module accessibility/performance/release certification belongs to M12.
