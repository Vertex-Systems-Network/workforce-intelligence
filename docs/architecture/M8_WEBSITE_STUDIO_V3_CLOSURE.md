# M8 — Website Studio V3 Functional Closure

Updated: 2026-08-20

M8 converts Website Studio into a reviewable staging-first visual publishing system while preserving WorkIntel permissions, immutable history, Media DAM governance and one public renderer contract.

## Closure capabilities

- Dedicated Pages / Layers / Blocks / Assets builder rail and Content / Design / Settings / Effects / SEO / Review inspector.
- Persistent mutable autosave separated from immutable version history.
- Explicit immutable Save and Stage for Review boundaries.
- Expiring, revocable share tokens render only the staged immutable version; later autosaves never change a reviewer link.
- Staging preview responses are private/no-store and explicitly noindex/nofollow.
- Page- and section-scoped review comments can be resolved or reopened without deleting history.
- Global reusable components maintain a materialized page-instance link index and propagate source updates only into mutable drafts, never into published/immutable history.
- Allowlisted dynamic tokens (`site.name`, `page.title`, `page.slug`, `page.language`, `year`) resolve through the same server renderer contract; unknown tokens remain visible and produce preflight warnings.
- Responsive tablet/mobile padding and content-width overrides render in both public media queries and explicit editor preview modes.
- Server preflight blocks unsafe URLs, empty pages, missing forms/media, duplicate section IDs and expired media rights; warnings cover accessibility, rights review, SEO and unknown dynamic bindings.
- Publishing consumes the staged immutable version and clears staging after successful publish.
- Archived pages must be restored before staging.
- Existing custom domains, forms, submissions, Media DAM references, version restore, effects, theme tokens and published delivery remain backward compatible.

## Certification still required

Functional/source/runtime closure is complete, but M8 remains at 95% until the target Laragon environment completes `npm run typecheck`, `npm run build`, the DB-backed `WebsiteStudioV3FlowTest`, Playwright keyboard/mobile/RTL coverage and the complete PHPUnit suite with the required PHP extensions/PDO driver.
