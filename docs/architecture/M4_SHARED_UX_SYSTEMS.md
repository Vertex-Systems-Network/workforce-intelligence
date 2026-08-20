# WorkIntel M4 Shared UX Systems

M4 turns recurring list, form, state and overlay behavior into one WorkIntel-owned interaction contract. Feature modules must consume these primitives instead of creating page-specific variants.

## Batch 1 contracts

- `FilterBar` — responsive search/filter/view/action composition.
- `DateRangeField` — paired WorkIntel date inputs with inclusive range constraints.
- `LoadingState` — compact async panel loading feedback.
- `ErrorState` — recoverable failure state with retry action.
- `EmptyState` — one explanatory empty-state treatment across feature pages.
- `DialogActions` — consistent cancel/submit/loading/danger footer actions.
- `ConfirmDialog` — app-owned confirmation for destructive and consequential actions.
- `DataGrid` V3 — backward-compatible table contract with V3 marker, shared date-range filters, skeleton states, saved views, filters, bulk actions and responsive mobile cards.

## Migration rules

1. Feature pages may not render `className="ui-empty"` directly; use `EmptyState`.
2. Feature pages may not render the generic `ui-toolbar` directly; use `FilterBar` or a module-specific semantic surface.
3. New destructive flows may not add browser `confirm()` calls. Existing confirmation debt is ratcheted at the current page baseline (51 calls) and will be migrated in later M4 batches.
4. Date ranges use `DateRangeField` rather than two unrelated date inputs.
5. Recoverable page-load failures should expose a retry through `ErrorState`.
6. Modal and drawer primary/cancel actions should converge on `DialogActions`.
7. Shared UX primitives remain inside `resources/js/design-system`; modules own business behavior, not generic interaction behavior.

## Batch 1 measured migration

- Page-owned `ui-empty`: 30 → 0.
- Page-owned generic `ui-toolbar`: 6 → 0.
- Six major list/time surfaces moved to `FilterBar`.
- Recoverable error screens standardized across core modules.
- Organization delete flow moved from browser confirmation to `ConfirmDialog`.

Later M4 batches will continue browser-confirm migration, form composition, media selection, dense data views and remaining page-level interaction CSS without changing domain behavior.
