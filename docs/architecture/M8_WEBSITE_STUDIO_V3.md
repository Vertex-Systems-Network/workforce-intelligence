# M8 — Website Studio V3

M8 converts the existing Website Portal Builder into a dedicated visual-builder workspace while preserving the same public renderer, permission model, Media DAM references and immutable publish history.

## Implemented through functional closure

- Dedicated three-pane builder shell with Pages / Layers / Blocks / Assets navigation.
- Responsive Desktop / Tablet / Mobile canvas, zoom, focus mode and dnd-kit layer ordering.
- Content / Design / Settings / Effects / SEO inspector taxonomy.
- Shared Media DAM picker for section, gallery and OpenGraph assets.
- Reusable component insertion and save workflow.
- Mutable server autosave in `website_page_drafts`; autosave never increments immutable versions.
- Explicit Save creates a new `website_page_versions` revision and clears the mutable autosave.
- Ctrl/Cmd+S, undo and redo editor shortcuts with bounded in-memory history.
- Server publish preflight for page structure, form references, media availability, Media DAM rights/alt text and unsafe URLs.
- Section effects with published-renderer parity and reduced-motion fallback.
- Expanded theme tokens for body size, heading scale, section spacing and button radius.
- Existing custom domains, lead forms, encrypted submissions, version restore, published renderer and SEO delivery remain intact.

## Closure additions

- Shareable, expiring and revocable staging preview tokens that pin an immutable staged version.
- Private/no-store and noindex/nofollow staging delivery.
- Page- and section-scoped review comments with resolve/reopen workflow.
- Linked global reusable components with draft-only source propagation.
- Allowlisted dynamic content bindings and unknown-token preflight warnings.
- Explicit tablet/mobile breakpoint overrides with accurate editor preview simulation.
- Staging-first publish lifecycle with archived-page safety guard.

## Remaining M8 scope

- Target Laragon typecheck/build and DB-backed flow certification.
- Playwright keyboard/mobile/RTL/browser certification.
