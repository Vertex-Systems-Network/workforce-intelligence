# DEV-08 Source Hygiene & Dead-Code Closure

DEV-08 converts source hygiene from an ad-hoc cleanup into a release contract. The browser import graph is rooted at `resources/js/app.tsx`; runtime TS/TSX/JS/JSX/CSS/JSON files must be reachable from that entrypoint unless they are explicitly documented standalone development/contract surfaces.

## Retired source

- `resources/js/pages/EmployeeProfile.tsx` — obsolete prototype with no runtime importer. The canonical `People` surface owns employee identity, workspace membership, profile-photo and security management.
- `resources/js/data.ts` — demo fixtures consumed only by the retired EmployeeProfile prototype.
- `resources/js/i18n/humanLabels.tsx` — unused localization helper; runtime labels are owned by the typed catalog and page-copy compatibility layer.

The M1 architecture manifest and CSV exports are historical snapshots and are not rewritten by DEV-08; they document that the prototype existed during M1. The current retirement contract is this DEV-08 ledger plus the source-graph gate, while the canonical `People` surface owns profile-photo and security workflows.

## Intentional standalone source

Two source files remain intentionally outside the `app.tsx` runtime graph:

- `design-system/ToolkitPreview.tsx` — internal Design System reference surface.
- `media/AvatarCropper.tsx` — retained media contract surface covered by lifecycle/media smoke tests even though the current People editor uses Media Library selection rather than a local crop flow.

They are explicit allowlisted roots, not silent orphan exceptions.

## Regression gate

`tools/dead-source-audit.mjs` fails when:

1. a new browser source file becomes unreachable from the runtime or documented standalone roots;
2. any DEV-08 retired source path returns;
3. production browser source introduces `console.log`, `console.debug`, or `debugger` residue;
4. the canonical `People` surface loses the profile-photo/security ownership markers that replaced the retired prototype.

The gate runs in `test`, `typecheck`, and `build` before dependency-backed TypeScript/Vite execution.
