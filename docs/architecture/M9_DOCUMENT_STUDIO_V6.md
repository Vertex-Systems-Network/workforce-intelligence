# M9 — Document Studio V6

Updated: 2026-08-20

## Purpose

Document Studio V6 converts the existing governed document-template engine into a multi-page studio without replacing its proven PDF generation, review, sharing, signing, security, or domain-context contracts. V6 is additive: legacy V4 flat schemas remain readable while newly saved designer state uses explicit page containers.

## V6 authoring architecture

The editor is organized as a professional document workspace:

- **Pages / Layers / Blocks / Assets** on the left.
- **Ruled multi-page server-rendered canvas** in the center.
- **Block / Page / Data / Review / Preflight** inspection on the right.
- Shared Media DAM selection and reusable document components remain first-class authoring sources.
- Tables, formulas, conditions, repeats, merge fields, columns, signatures, QR/barcodes, page numbering and existing governed blocks remain available through the V4-compatible block engine.

## Page model and compatibility

V6 introduces a root `page` block. Every authored page owns a bounded `children` collection and stable page ID. A V6 schema cannot mix page containers with legacy root blocks. Legacy flat templates continue to render through a compatibility page and are normalized to logical pages when opened in the V6 client; explicit save persists the V6 schema.

Limits remain defensive: 50 authored pages, 300 total nested blocks, eight levels of nesting, and existing block-specific validation.

## Autosave and immutable versions

`document_template_drafts` stores exactly one mutable autosave per template. Autosave validates the same content schema and settings as an explicit template save but does **not** increment `current_version` or create a `document_template_versions` row.

Explicit Save remains the immutable checkpoint. When a real template change is saved, the template version increments, a snapshot is created, and the mutable autosave draft is deleted. Discard Draft deletes only mutable state.

## Server preflight

V6 preflight accepts persisted or unsaved designer state and reports page/block counts plus blocking/warning issues. Current checks include:

- empty documents and empty pages;
- authored-page limit;
- missing or inaccessible Media DAM assets;
- missing image alternative text;
- missing reusable components;
- missing table/repeat data sources;
- empty formulas;
- disabled header/footer region warning.

Preflight uses workspace-scoped references and the same validated schema/settings contract as save and rendering.

## Batch generation

The V6 batch endpoint generates governed documents for 1–50 explicit source IDs. Each record uses the existing `DocumentTemplateService::generate` path, preserving context scoping, encrypted render context, PDF generation, automation behavior and generated-document governance. One failed source does not erase successful sibling generations.

## Existing governance preserved

M9 does not replace these V4 systems:

- immutable version comparison;
- generated-document review/approval;
- internal comments;
- expiring hash-only share links;
- internal/external signature requests;
- locked signed documents;
- PDF renderer hardening and Unicode/RTL layers;
- document-domain permission checks.

## Release gates

`tools/document-studio-v6-audit.mjs` is mandatory in `npm test`, `npm run typecheck`, `npm run build`, `verify-release.cmd`, and `verify-clean-install.cmd`.

Target Laragon certification also runs `DocumentStudioV6FlowTest`, full PHPUnit, TypeScript, production build and browser accessibility certification. Sandbox dependency limitations are not treated as passes.

## Advanced authoring — Batch 2

M9 Batch 2 adds reusable authoring systems above the multi-page foundation:

- **Brand Kits** persist workspace-scoped primary/secondary/accent colors, supported fonts and optional Media DAM logo references. Templates retain a `brand_kit_id`; the renderer resolves the current linked kit at render time so shared brand updates propagate without rewriting immutable template versions.
- **Page Masters** persist normalized page margins, header, footer and watermark settings. Linked masters are resolved by the server renderer at preview/PDF time. Templates can still detach by clearing the link and retaining local settings.
- **Reusable components** now expose a monotonically increasing source version. Linked reusable blocks keep rendering the current shared source. The V6 client can deliberately detach one linked instance into re-keyed local blocks when page-specific divergence is required.
- **Advanced tables** support bounded column widths and presentation formats (`text`, `number`, `currency`, `date`, `percent`). Validation rejects unsafe formats/widths and preflight warns when authored widths exceed 100%.
- **Authoring guides** add optional canvas rulers and printable-margin guides without changing generated output.
- **Workflow defaults** let template authors declare review, approval and signature expectations. Preflight blocks a signature-required template that has no signature block.
- **Persistent large batches** use `document_batch_jobs`. Batches of 1–50 may remain synchronous for immediate work; 51–500 are persisted and processed by `workintel:process-document-batches` in bounded scheduler chunks. Per-source results are retained so one failure does not erase successful sibling generations.

The advanced resource API is workspace scoped and protected by the existing document permissions. Brand/page-master deletion is blocked while active template settings still reference the resource.

## Final closure hardening

The final M9 closure pass strengthens production behavior without rewriting existing immutable document history:

- **Retry-safe persistent batches** accept an optional workspace-scoped client request ID. The database unique key plus `firstOrCreate` semantics make concurrent HTTP retries idempotent, while stale running jobs use heartbeat-based recovery without rewinding the committed source cursor.
- **Batch observability** records heartbeat, attempt count and the latest bounded error while the existing per-source result ledger remains intact. Scheduler recovery never turns a committed source back into pending work.
- **Brand Kit logos** are selected from the shared Media DAM. Linked Brand Kit logos are used as the fallback for logo blocks that do not carry an explicit asset. Trashed/unavailable linked logos fail preflight instead of silently disappearing.
- **Per-page masters** allow an authored logical page to override the document-level Page Master and optionally override margins/background. The same page-resolved settings are used by server preview and generated PDF HTML.
- **Generated workflow policy snapshots** persist review/approval/signature requirements and signer role in `render_metadata` at generation time. Later template edits cannot silently change governance for an already generated document.
- **Workflow enforcement** honors required review before approval/signature, required approval before signature/final lock, and required completed signatures before final lock. The generated-document UI reads the immutable policy snapshot and disables invalid actions before the server rejects them.
- **Resource deletion safety** checks saved templates, logical pages and mutable drafts structurally instead of relying on JSON string matching.
- The historical Laragon clean-seed regression around `DocumentTemplateService::create()` is guarded: validated `$settings` is explicitly captured by the database transaction closure.

M9 is functionally closed at 95%. The remaining 5% is target-environment certification: full MySQL-backed feature/PHPUnit execution, installed-node TypeScript/production build, and Playwright keyboard/mobile/RTL/accessibility coverage. Those target-only gates remain under M9/M12 and are not represented as passes when unavailable in the sandbox.
