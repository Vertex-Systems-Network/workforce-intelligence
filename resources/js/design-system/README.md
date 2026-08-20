# WorkIntel Design System V1

`resources/js/design-system` is the **single source of truth** for reusable interactive UI primitives. Feature modules consume this layer instead of rendering raw interactive/media elements or inventing parallel component systems.

## Files

- `index.tsx` — authoritative React primitives and composed controls.
- `tokens.css` — semantic WorkIntel spacing, surface, control, radius, shadow, z-index, focus and motion aliases.
- `toolkit.css` — visual component states, overlays, tables, forms, application shell and responsive behavior.
- `accessibility.ts` — focus and keyboard helpers.
- `toast.tsx` — shared notification viewport.
- `PageCustomization.tsx` — per-user density/layout preferences.
- `ToolkitPreview.tsx` — visual internal reference surface.

## Feature-code rules

1. Import reusable UI from `design-system`; feature code must not recreate raw interactive, form, link, table or media primitives.
2. `lucide-react` is the only interface icon library.
3. No handwritten SVG action icons in feature code.
4. Use semantic `--wi-*` tokens and shared layout contracts before page-specific constants.
5. New feature-level inline style objects are blocked by the M2 ratchet. Batch 2 reduced the baseline from 1,308 to 545; remaining module-specific/dynamic debt must only decrease.
6. The public Website renderer is a deliberate exception for semantic generated website markup.
7. A new visual primitive belongs here first, with keyboard/focus/disabled/loading/error/RTL/reduced-motion/mobile behavior, then modules may consume it.
8. Use `Box`, `Stack`, `Inline`, `Grid`, `Text`, `Form`, `Label`, `Option` and `Link` instead of page-owned static layout/form styling where applicable.

See `docs/architecture/M2_DESIGN_SYSTEM.md` for migration status and acceptance criteria.


## M4 Shared UX

Feature modules use `FilterBar`, `DateRangeField`, `LoadingState`, `ErrorState`, `DialogActions`, `ConfirmDialog` and DataGrid V3 for recurring interaction patterns. Page-owned `ui-empty` and generic `ui-toolbar` implementations are release-blocked.
