# WorkIntel AI Execution Contract

This file is the canonical execution guide for AI/code agents working in this repository. Read it before changing source, tests, workflows, architecture docs, or release status.

## Product and architecture

WorkIntel is one domain-agnostic Laravel 13 + React/TypeScript application. Laravel owns web/API routing, workspace authorization, persistence and backend policies; React owns the browser surfaces served by the same application. Do not split the product into an unrelated second frontend/backend application.

Canonical architecture and flow diagrams live in `docs/architecture/SYSTEM_ARCHITECTURE_AND_FLOW.md`. Code-quality/accessibility policy lives in `docs/architecture/CODE_QUALITY_AND_ACCESSIBILITY.md`.

Important surfaces include:

- public marketing website
- authenticated workspace shell
- Client Portal
- Seller Platform
- public Website Studio renderer
- public document-signing surface
- desktop/browser tracking agents and their authenticated ingestion APIs

Frontend visibility is never the security boundary. Backend workspace/module/entitlement/permission checks remain authoritative.

## UI and interaction rules

- Reuse primitives from `resources/js/design-system`; do not bypass the design system with raw interactive HTML where a shared primitive exists.
- Keep operational body text readable. Do not introduce 8–11px working text for labels, actions, help text, statuses, payment information, chat content or navigation.
- Preserve visible keyboard focus, accessible names, logical RTL layout, reduced-motion behavior, reflow and coarse-pointer/touch sizing.
- Primary touch targets should remain at least 44px where the coarse-pointer contract applies.
- Do not create fake links (`href="#"`, `javascript:void(0)`), empty interaction handlers, browser-native `window.alert/prompt/confirm` workflows, or unfinished TODO/FIXME/HACK comments in production browser source.
- Do not reintroduce hidden horizontal-scroll navigation for the public mobile header.
- Marketing product visuals are illustrative UI representations unless a real product screenshot is explicitly supplied. Never describe generated/interface illustrations as customer screenshots.
- Keep onboarding contextual and non-blocking; operational workflows must not be covered by first-run guidance.

## Source hygiene

`tools/dead-source-audit.mjs` is a release contract. It rejects unreachable browser source, production debug residue, dead interaction patterns, editor/temp artifacts, accidental root placeholders and zero-byte public runtime assets.

Do not commit:

- `.bak`, `.backup`, `.old`, `.orig`, `.rej`, `.tmp`, `.temp`, `.swp`, `.swo` or editor backup files
- `.DS_Store`, `Thumbs.db`, `desktop.ini`
- accidental placeholder files such as `__noop__`
- empty public runtime assets
- `console.log`, `console.debug` or `debugger` in production browser source

Historical migration filenames are database history and must not be casually renamed during cleanup.

## Code-quality commands

During implementation, use the source-quality contract first:

```bash
npm run quality
```

Before final browser certification, use:

```bash
npm run quality:full
```

Key automated quality layers include:

- TypeScript `tsc --noEmit`
- frontend/source/design-system/module/dead-source audits
- PHP and JS/TS documentation audits
- PHPUnit and frontend contract tests
- performance budgets
- Laravel Pint for changed PHP files
- GitHub CodeQL JavaScript/TypeScript `security-and-quality`

For an individual changed PHP file:

```bash
vendor/bin/pint --test path/to/ChangedFile.php
```

Do not weaken an audit merely to make a change pass. Fix the source defect or update the contract only when the intended architecture genuinely changed.

## WCAG / W3C / WAVE truthfulness

Repository accessibility checks are W3C/WCAG-oriented automated evidence; they are not formal accessibility certification and must not be described as 100% accessibility coverage.

The real WebAIM WAVE adapter is:

```bash
npm run accessibility:wave
```

It requires `WAVE_API_KEY` and a publicly reachable `WORKINTEL_WAVE_URL`. Hosted WAVE cannot scan localhost, `.test`, `.local` or another private-only development URL. Never claim “WAVE passed” unless that external scan actually ran successfully against the stated reachable target.

## Browser and runner certification order

Do implementation, source cleanup and non-browser quality work first. The expensive runner/browser matrix is the final release step so it certifies a settled head rather than a sequence of intermediate UI commits.

Final release certification must cover the exact final PR head:

1. WorkIntel Code Quality: CodeQL + changed-PHP Pint.
2. WorkIntel CI: source/tests/build/migrations/seeds/PHPUnit/production doctors/browser/accessibility/MySQL/routes/scheduler as defined by the workflow.
3. WorkIntel Windows Certification: GitHub-hosted Windows plus required installed Chrome/Edge/Firefox browser/accessibility gates.
4. Required `governance` status when imposed by the repository/org ruleset.
5. Optional external WAVE scan reported separately when a public release candidate and credentials exist.

Do not merge because an older SHA was green. Do not bypass, fake, skip or weaken required governance/browser/accessibility statuses. Merge only after the exact final head satisfies the actual required statuses.

## Runner policy

The active certification workflow is GitHub-hosted. Do not reintroduce self-hosted/Laragon runner requirements into repository release governance unless the project scope is explicitly changed. Local Laragon can remain a development environment without becoming the required release authority.

## Change discipline

- Prefer small, coherent commits with descriptive messages.
- Keep architecture/status documentation aligned with real implementation.
- Do not claim a test/tool passed without seeing the result for the exact code being certified.
- If a browser test exposes a real defect, fix the defect. If a test itself is invalid, repair the test without reducing intended coverage.
- Never delete legitimate source merely because it looks old; prove it is unreachable/retired first.
