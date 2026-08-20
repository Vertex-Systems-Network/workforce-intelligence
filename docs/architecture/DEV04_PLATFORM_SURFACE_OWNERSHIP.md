# DEV-04 — Platform Surface Ownership Closure

## Runtime decision

- The tenant workspace no longer owns a `platform` page ID or `Platform.tsx` page component.
- Platform operators enter the separate seller/platform application shell at `/seller`.
- The account menu remains the tenant-shell handoff point for authorized platform operators.
- `pageShell`, navigation translations, access/page-module mappings, page customization and tenant rendering no longer carry a legacy Platform destination.

## Backend compatibility

`/api/v1/platform/*` routes are intentionally retained. They are workspace-scoped backend/domain contracts for branding, domains, partners, add-ons, templates, imports and sandboxes. DEV-04 removes the obsolete tenant UI ownership; it does not perform a backend API deletion or contract migration.

## Historical inventories

M1 inventory CSV/route artifacts remain historical baseline evidence. `workintel-modules.json` retains the historical `platform` classification row with `decision: REMOVE`, `target: platform-console`, and `canonicalPath: /seller` so migration intent stays auditable without reintroducing the page at runtime.
