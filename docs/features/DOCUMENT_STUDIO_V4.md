# Document Studio V4

Document Studio V4 extends WorkIntel's governed document engine into a paged visual workspace rather than introducing a parallel template system. Existing immutable template versions, generated documents, domain permissions and private storage remain authoritative.

## Visual designer

The designer has four working surfaces: **Designer**, **Generated**, **Components** and **Variables**. Designer uses a three-pane layout with templates/toolbox on the left, live paged preview in the center, and block/page/comments inspection on the right. Top-level block ordering uses dnd-kit; document layout is not implemented with GridStack.

Supported V4 blocks include headings, plain and rich text, dynamic fields, media, key/value data, tables, totals, formulas, conditions, repeats, columns, callouts, stamps, QR/barcodes, signatures, reusable components, dividers, spacing, page breaks, page-number markers and footer content. Nested schemas are depth- and count-limited and block IDs must be unique.

## Dynamic data and logic

Dynamic values use server-owned context paths. Conditions support guarded comparison operators, repeats have explicit collection sources and maximum item counts, and formulas use the DocumentExpressionEngine rather than PHP `eval`. Legacy condition aliases remain readable so older schemas continue to render.

Images selected from the Media Library remain workspace scoped and are embedded through the document renderer rather than exposing private storage paths. Rich HTML is sanitized through an allowlist before rendering.

## Paged preview and PDF

HTML preview uses paged print CSS with A4/Letter paper sizes, portrait/landscape orientation, margins, page background, repeating header/footer configuration, watermarking and RTL direction metadata.

`DOCUMENT_PDF_DRIVER=auto` prefers an installed Chrome, Chromium or Microsoft Edge binary for Unicode-capable headless PDF generation. `DOCUMENT_CHROMIUM_BINARY` can pin the binary. If no browser is available, auto mode falls back to the legacy PDF renderer; the generated-document metadata records which driver was used and whether it was Unicode capable.

QR and Code128 blocks use an optional local Python adapter (`tools/document-code-svg.py`) when compatible `qrcode`/ReportLab packages are available. Missing adapters are represented explicitly rather than silently pretending an invalid fallback is a scannable code.

## Governance and signing

Generated documents support review request, approval, rejection, comments, locking, secure sharing and external signatures. Review actions create immutable workflow events.

Share and signature tokens are returned only at creation and are stored as hashes. Links can expire or be revoked and share links can enforce maximum views. Signature requests support typed or drawn signatures with explicit consent. External signing uses the dedicated `/document-sign/{token}` web surface and does not require workspace authentication.

The generation-time render context is encrypted and retained with the generated document. When all required signatures complete, the final document can be regenerated from that immutable snapshot instead of mutable live business data, then marked signed/locked.

## Permissions

V4 adds granular permissions for document sharing, signing, approval and reusable-component management in addition to the existing document view/manage permissions. Public token endpoints do not bypass workspace controls when their tokens are created; access is constrained to the generated artifact represented by the hash-only token.

## Verification

Dependency-free source verification:

```bat
php tools\document-studio-v4-smoke.php
npm test
```

With the runtime environment installed:

```bat
php artisan test --filter=DocumentStudioV4ContractTest
php artisan test --filter=DocumentStudioV4FlowTest
php artisan workintel:document-v4-doctor --json
```

The release scripts run these gates as part of the normal migration, seed, PHPUnit, typecheck and production build verification chain.
