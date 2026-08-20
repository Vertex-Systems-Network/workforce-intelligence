# DEV-08 Source Hygiene & Dead-Code Closure

DEV-08 converts source hygiene from an ad-hoc cleanup into a release contract. The browser import graph is rooted at `resources/js/app.tsx`; runtime TS/TSX/JS/JSX/CSS/JSON files must be reachable from that entrypoint. DEV-08 closes all currently known standalone-source exceptions, so an unreachable browser source file is release-blocking rather than silently allowlisted.

## Retired source

- `resources/js/pages/EmployeeProfile.tsx` — obsolete prototype with no runtime importer. The canonical `People` surface owns employee identity, workspace membership, profile-photo and security management.
- `resources/js/data.ts` — demo fixtures consumed only by the retired EmployeeProfile prototype.
- `resources/js/i18n/humanLabels.tsx` — unused localization helper; runtime labels are owned by the typed catalog and page-copy compatibility layer.
- `resources/js/design-system/ToolkitPreview.tsx` — standalone Design System showcase with no application, build or release entrypoint. Shared Design System contracts remain covered by the active design-system audits and frontend tests.
- `resources/js/media/AvatarCropper.tsx` — legacy local crop implementation with no runtime importer. Current People avatar selection is owned by the shared Media Library workflow.

The M1 architecture manifest and CSV exports are historical snapshots and are not rewritten by DEV-08; they document earlier architecture states. The current retirement contract is this DEV-08 ledger plus the source-graph gate, while the canonical `People` surface owns profile-photo and security workflows.

## Zero standalone exceptions

`tools/dead-source-audit.mjs` currently has an empty standalone-root set. New development/demo browser files must either become part of a real runtime graph or be explicitly justified before an exception is introduced. This prevents reference previews, prototypes and abandoned helpers from accumulating as permanent source debt.

## Regression gate

`tools/dead-source-audit.mjs` fails when:

1. a browser source file becomes unreachable from the runtime graph;
2. any DEV-08 retired source path returns;
3. production browser source introduces `console.log`, `console.debug`, or `debugger` residue;
4. the canonical `People` surface loses the profile-photo/security ownership markers that replaced the retired prototype.

The gate runs in `test`, `typecheck`, and `build` before dependency-backed TypeScript/Vite execution.
