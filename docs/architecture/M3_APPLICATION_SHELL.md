# M3 — Application Shell & Information Architecture

## Batch 1 outcome

The authenticated workspace shell now consumes the M1 business-module taxonomy instead of presenting legacy feature buckets. The migration preserves existing page IDs, permission checks and routes while changing how users discover and understand the product.

### Implemented

- Module-first collapsible sidebar for all workspace roles.
- Role navigation regrouped by M1 ownership; Approvals and Automations are under Work Management instead of unrelated buckets.
- Platform Console removed from tenant workspace navigation; authorized operators reach the dedicated `/seller` surface from the account menu.
- Central `moduleCatalog.ts` owns page icons, module ownership, search aliases and plain-language purpose descriptions for all 40 shell page IDs.
- Persistent shell context bar shows module → page ownership and one-line purpose copy.
- Home dashboard exposes a role-aware Workspace Areas directory so users choose a business area before hunting through pages.
- Command Palette searches translated page labels plus module names, descriptions and user vocabulary aliases.
- Shell pages use URL hashes (`/app#tasks`, `/app#attendance`, etc.) so refresh, copied links and browser Back/Forward preserve navigation state without requiring new server routes.
- M3 architecture audit is release-enforced in npm test/typecheck/build and Windows release/clean-install verification.

## Compatibility decisions

The tenant shell no longer owns a `platform` page ID. DEV-04 retired the legacy `Platform.tsx` compatibility page after reference-graph verification; authorized platform operators reach the dedicated `/seller` app shell from the account menu. Workspace `/api/v1/platform/*` endpoints remain available as backend compatibility/domain contracts and are not exposed as a tenant navigation destination.

## Remaining M3 work

1. Dedicated module home pages with module-specific KPIs, recent work and quick actions.
2. Global entity search across people, tasks, projects, clients and documents, not only page navigation.
3. Mobile sidebar as an overlay/drawer instead of a permanently narrow rail.
4. Recently visited/favorite destinations and role-aware quick actions in command search.
5. Contextual module switching and refined breadcrumbs for nested/detail routes.
6. Target-environment TypeScript/Vite/browser certification.

## Acceptance status

Batch 1 completes 55% of M3. The module taxonomy, user explanations, page discovery and deep-link shell contracts are implemented and release guarded; dedicated module homes, global entity search and mobile shell refinement remain.
