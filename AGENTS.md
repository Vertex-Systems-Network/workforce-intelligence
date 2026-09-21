# WorkIntel AI Execution Contract

This file is the canonical execution guide for AI/code agents working in this repository. Read it before changing source, tests, workflows, architecture docs, or release status.

## Authority hierarchy and current implementation lock

Repository-native authority outranks chat memory, generated plans, Linear comments and AI-authored summaries. Before material work, resolve authority in this order:

1. protected `main` and current Git state;
2. this `AGENTS.md` execution contract;
3. explicit owner-approved GitHub issue/PR scope and repository architecture/status documents;
4. applicable security, quality, release and migration contracts;
5. Linear/task mirrors and AI continuity notes as non-authoritative coordination aids.

A lower layer cannot broaden a higher layer. One repository cannot authorize changes in another repository.

At the current post-M13 checkpoint, no repository-native M14 product implementation authority exists. GitHub Issue #50 is the current planning/product-authority gate only; it authorizes no product implementation until a concrete owner-approved scope names the acceptance evidence and exact starting Git state. Do not invent M14 features, migrations, APIs, UI, release changes or product scope until that authority exists.

## Compact durable state and source of truth

The compact durable AI state lives at `docs/ai-state`. This is the repository's concrete `COMPACT_STATE_PATH`.

On every start, `continue`, resume, interrupted session, connector/tool failure, previous message-delivery timeout, merge, or material state transition:

1. read `docs/ai-state/CURRENT-STATE.yaml`;
2. read `docs/ai-state/LAST-CHECKPOINT.md`;
3. resolve the exact current default/protected `main` SHA;
4. reconcile OPEN GitHub Issues first;
5. reconcile OPEN PRs second;
6. reconcile `docs/ai-state/DETERMINISTIC-CLAIMS.yaml`, `docs/ai-state/COORDINATION-QUEUE.yaml`, and `benchmarks/runner/registry.json`;
7. inspect only commits/files needed for the active milestone or drift;
8. read large historical checkpoints only for a specific historical fact, evidence item, or conflict.

Compact state is a resume index and never overrides current repository/runtime truth. Chat memory is never authority. A missing or timed-out ChatGPT response is not evidence that repository work failed; verify what persisted before repeating anything.

## One user turn = one logical milestone

By default, one user `continue` or `resume` turn performs exactly one bounded logical engineering milestone.

Examples: reconcile/close one accepted PR; implement one coherent change and persist it; perform one exact-head verification/merge decision; reconcile durable shared state after a merge.

Do not chain broad audit -> multiple implementations -> repeated CI polling -> merge -> post-merge audit -> unrelated next task in one turn. Security/incident recovery may contain tightly coupled actions only when splitting them would reduce safety.

## Issues / PRs first — hard gate

New product/development work is forbidden while an accepted actionable OPEN Issue or PR is being bypassed.

Before new development:

`Compact State -> Exact Main -> OPEN Issues -> OPEN PRs -> Deterministic Claims/Coordination Queue -> Runner Benchmark -> New Work`

An Issue already represented by an accepted PR is one work path. Finish/review/fix that PR instead of creating duplicate implementation. Merge only dependency-safe, exact-head, review-clean work.

## Timeout / remote-call budget

Batch related read-only calls when supported and read only what the active milestone needs.

By default perform at most one consolidated CI/status refresh per milestone. Never tight-poll CI/workflows/deployments/providers/status endpoints, repeatedly fetch unchanged workflow state while waiting, or rerun a workflow merely because a ChatGPT/UI/message response timed out.

Before final exact-head CI observation, persist the milestone as `VERIFYING` or `WAITING_EXTERNAL` when remote checks are expected. If required CI is still running after the consolidated refresh, do not create another source commit solely to record pending CI; preserve durable state, record run IDs on a PR/Issue surface when possible without modifying the certified source head, report pending, and end the milestone.

A second refresh in the same milestone is allowed only after a material security, merge, incident/recovery, or provider state transition requires it for a safe decision. Record that exception durably.

## State drift reconciliation

At every resume reconcile stale main SHA, merged/closed/reopened Issues/PRs, branch/PR head movement, coordination queue, Runner task-definition status, applicable external result envelopes/evidence, and relevant commits since the recorded anchor. A merged item must not remain `PENDING_MERGE`. An older green SHA cannot certify a newer head.

## Start / resume protocol

Before every material read-write sequence:

1. execute the compact-state/source-of-truth order above;
2. identify `repository + issue/scope + protected-main SHA + working branch + current branch/PR head`;
3. re-read this contract and the exact issue/specification that grants authority;
4. compare working state with current protected `main`;
5. inspect required checks, review state, unresolved conversations, and authorization boundaries when a PR exists;
6. stop and rehydrate if main/head/scope/governance/authorization moved materially;
7. never treat a previously green SHA as evidence for a newer head.

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

## Mandatory impact analysis

Before implementation, explicitly record:

- **Affected:** files, modules, APIs, jobs, browser/agent surfaces, data models and release artifacts that will change;
- **Unaffected:** adjacent contracts that must remain behaviorally unchanged;
- **Risk:** security/privacy, tenant isolation, data loss, billing/payroll, tracking, release trust, browser/agent compatibility and operational risk;
- **Migration:** schema/data/config/version transitions and existing-data compatibility, or explicit `N/A` with reason;
- **Rollback:** safe reversal/forward-fix strategy and irreversible boundaries;
- **Verification:** exact automated and, where needed, real-target/browser evidence required to close the change.

If these cannot be stated coherently, the scope is not ready for implementation.

## Research and decision evidence

For material technical, security, protocol, browser, framework, package, legal/standards or deployment decisions that depend on external facts, prefer current primary sources: official documentation, standards, vendor advisories and upstream release notes. Record the decision-relevant conclusion rather than copying large source passages.

Do not treat an AI answer, search snippet, old blog post or community comment as authoritative evidence for a high-impact decision. If external evidence is unavailable or conflicting, mark the point `Not Verified` and avoid silently converting uncertainty into architecture.

## Threat-model / FMEA triggers

Perform explicit abuse-case and failure-mode analysis before changes touching any of these areas:

- authentication, authorization, roles, tenant/workspace boundaries or impersonation;
- employee monitoring, screenshots, activity/browser capture, privacy, retention or exports;
- payroll/salary/payment/billing calculations or financial state;
- enrollment codes, device/browser tokens, updater/download trust or release packaging;
- public signing, portal exposure, upload/download paths or externally reachable callbacks;
- migrations, destructive data operations, bulk jobs or cross-module writes;
- secrets, credentials, encryption, audit/security events or production diagnostics.

At minimum evaluate unauthorized access, cross-tenant access, replay, tampering, partial failure, stale state, privilege escalation, data leakage, rollback failure and operator misuse. High-severity unresolved failure modes block release.

## Data, migration and concurrency discipline

For schema/data changes:

- preserve existing production data unless explicit destructive authority exists;
- explicitly review idempotency, supported transaction boundaries, partial execution, concurrency and retry behavior;
- treat apply-success followed by marker/status-write failure as a first-class crash-recovery case; never assume apply-then-mark is automatically safe;
- define rollback/restore or forward-recovery behavior for destructive and partially applied operations;
- record backup/snapshot requirements when recovery depends on them;
- keep destructive migration authority separate and explicit;
- migrations must be deterministic, reviewable and safe for the supported database matrix;
- define backward/forward compatibility across deploy boundaries when application and schema may not switch atomically;
- avoid hidden manual SQL as a required production step;
- test fresh install and supported upgrade paths where applicable;
- define transaction boundaries for multi-record invariants;
- consider retries, duplicate delivery, concurrent writes, locking and idempotency rather than assuming single-threaded execution;
- never rename historical migration filenames casually because they are database history.

For money/payroll/timekeeping, retain exact domain semantics and test rounding, timezone/date-boundary and duplicate-event cases explicitly.

## Reliability, timeout and recovery rules

Network, queue, browser-agent and external-service operations must define bounded timeout/retry behavior appropriate to the operation. Retries must not duplicate irreversible effects. Prefer idempotency keys/deduplication or state-machine guards where repeated delivery is possible.

Model partial failure explicitly: what happens if the request succeeds but acknowledgment fails, a worker restarts midway, a release download is interrupted, or one side of a multi-step operation commits first? Recovery must be deterministic and observable.

Do not create infinite retry loops, silent fallback to less secure behavior or retry storms. Repeated identical failure without new evidence triggers the circuit breaker below.

## Diagnostics and sensitive-data redaction

Operational diagnostics should carry stable machine-readable error identity and correlation/request context where practical. Logs and error payloads must not expose passwords, tokens, enrollment codes, raw authorization headers, private signing material, sensitive screenshot/browser payloads or unnecessary personal data.

Security/audit evidence and ordinary application logs are distinct concerns. A successful log write does not prove a security control executed, and verbose logging is not a substitute for structured failure handling.

## Dependency and supply-chain intake

Before adding or materially upgrading a dependency, inspect:

- whether the capability is genuinely needed versus existing platform/runtime support;
- maintenance/release health and relevant advisories;
- license/provenance compatibility;
- install/postinstall scripts and build-time execution;
- transitive dependency, runtime and bundle-size impact;
- browser/Node/PHP/platform compatibility;
- lockfile diff and rollback path.

Pin/lock according to repository policy. Do not use an unreviewed dependency merely to shorten implementation. Release-package provenance and immutable-version rules must not be weakened to absorb dependency churn.

## CI / supply-chain security

Where applicable, pin third-party CI actions to immutable revisions, disable unnecessary credential persistence, use least-privilege workflow permissions, avoid dangerous `pull_request_target` execution without a separately reviewed exception, enforce dependency-security gates, and do not run untrusted lifecycle scripts merely to regenerate lockfiles unless explicitly reviewed.

Keep production/distributable dependency audits separate from development-tooling audits where appropriate. CI/Runner optimization must never weaken branch protection, required checks, provenance, security gates, or release trust.

## ADR and technical-debt classification

Use an ADR or explicit architecture/change-control record before changing a durable architectural boundary, security model, ownership model, release trust model, persistence model, public contract or deployment topology.

If a workaround is intentionally temporary, record owner, reason, risk, removal condition and follow-up issue. Do not hide architectural debt behind comments or silently make a temporary exception permanent.

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

### Runner benchmark backlog

The canonical backlog is `benchmarks/runner/registry.json`; the human workflow is `docs/release/RUNNER_BENCHMARK_REGISTER.md`. Validate it with `npm run audit:runner-benchmarks`.

Every material remote/container/browser/runtime/full-regression/performance workload must record: stable task ID; source Issue/PR/work package; command/workflow; exact source identity; environment/matrix/input/fixture identity; authorization state; security-critical classification; merge-blocking classification; expected runner-time budget; deterministic dedup key; status; and immutable terminal evidence.

Runner registration NEVER grants execution authority. Consumed, expired, historical, destructive, provider, production, deployment, release, or formal-runtime authorization must NEVER be inferred or silently reused.

Safe non-blocking Runner work defaults to `final-runner-batch`. These remain `immediate` once the active milestone actually requires them: security-critical validation; exact-head merge-required checks; migration/auth/secrets/data-safety checks; current-change integration-safety checks; incident/recovery checks.

`immediate` means "do not defer past the milestone that needs the result"; it does not mean "run without authority". If current authority is absent, mark the task `blocked`.

Before execution, resolve the exact candidate SHA outside the candidate source tree, verify deduplication, verify current authorization, and persist `VERIFYING`/`WAITING_EXTERNAL` when remote observation is expected.

The committed Runner registry is a **task-definition register**, not the terminal exact-head result ledger. Never commit the candidate SHA or terminal PASS/FAIL evidence into the same candidate source branch merely to record certification: that evidence commit would change the SHA and invalidate the head it claims to certify.

Exact-head runtime state is recorded in a machine-readable result envelope conforming to `benchmarks/runner/result-envelope.schema.json`. Store that envelope on a non-source evidence surface such as an immutable GitHub Actions artifact, PR/Issue evidence attachment/comment carrying the exact JSON envelope, or another explicitly approved immutable evidence store. Validate a local envelope with `npm run validate:runner-result -- <path>`.

A terminal result envelope must contain the stable Runner task ID, exact candidate repository/ref/SHA, computed deterministic dedup key, current authorization reference, execution identity, terminal status, timestamps, and immutable evidence references. A head move requires a new envelope/dedup key; older envelopes remain historical evidence only.

Cheap local/source checks must not be deferred into Runner Benchmark.

## Runner policy

The active certification workflow is GitHub-hosted. Do not reintroduce self-hosted/Laragon runner requirements into repository release governance unless the project scope is explicitly changed. Local Laragon can remain a development environment without becoming the required release authority.

## Repeated-failure circuit breaker

Never blind-retry the same failing action indefinitely. After a repeated or reproducible failure:

1. capture the exact failing SHA, command/check, error class and environment;
2. determine whether the defect is source, test, environment, dependency, data or authority/state related;
3. change the hypothesis or implementation before retrying;
4. retain meaningful diagnostic failures instead of rewriting history as PASS.

If the failure cannot be diagnosed safely, stop the affected lane as `Blocked`/`Not Verified`; do not weaken the check or broaden scope to make progress appear green.

## Change discipline

- Prefer small, coherent commits with descriptive messages.
- Keep architecture/status documentation aligned with real implementation.
- Do not claim a test/tool passed without seeing the result for the exact code being certified.
- If a browser test exposes a real defect, fix the defect. If a test itself is invalid, repair the test without reducing intended coverage.
- Never delete legitimate source merely because it looks old; prove it is unreachable/retired first.
- Protected `main` is not an AI scratch branch; use a scope-specific branch/PR for writes.
- Do not allow a planning issue, generated report or AI-authored prose to self-promote product implementation authority or completion state.

## Durable state before reporting completion

Before saying a meaningful milestone is `COMPLETE`, `BLOCKED`, `VERIFYING`, or `WAITING_EXTERNAL`, reconcile `docs/ai-state/CURRENT-STATE.yaml`, `docs/ai-state/LAST-CHECKPOINT.md`, rolling `docs/ai-state/EXECUTION-JOURNAL.md`, the coordination queue when changed, and Runner Benchmark when changed.

`CURRENT-STATE.yaml` must contain observed main SHA, active Issue, active PR, active branch, current milestone, milestone status, last completed milestone, exact next safe action, pending Runner IDs, blocked Runner IDs, current blockers, and timeout-control settings.

If durable state cannot be written, do not claim the milestone fully complete.

Compact limits: `CURRENT-STATE.yaml` <= 12 KiB; `LAST-CHECKPOINT.md` <= 16 KiB; `EXECUTION-JOURNAL.md` <= 32 KiB. The journal is rolling; archive older detail when needed.

## README / large status dashboards

Update a large public/module dashboard only when underlying module lifecycle/progress/timeline/public delivery truth changed or a terminal product/integration closeout is being reported. Governance/security/coordination-only cycles update compact state and relevant governance records without rewriting a large dashboard merely to create churn.

## Final user-facing response

Keep completion compact and factual: repository; active/completed milestone; Issue/PR/commit evidence; CI state; blockers; exact next safe action. Do not hide unfinished CI, review, state reconciliation, or authorization behind a success statement.

## Recovery after message-delivery timeout

After `Message delivery timed out. Please try again.`: read compact durable state; resolve exact current main; inspect the previously active Issue/PR; determine what actually persisted; reconcile queue and Runner Benchmark; continue only the next unfinished milestone. Never redo a merge, deployment, destructive action, migration, provider call, or formal runtime execution solely because the previous response was not delivered.

## Required closeout contract

Every material AI/code-agent checkpoint must end with four explicit states:

- **Verified:** exact evidence actually observed for the current SHA/state;
- **Not Verified:** required evidence not run, unavailable, stale or belonging to a different SHA/target;
- **Known Risk:** unresolved technical/security/operational assumptions that remain after the work;
- **Next Action:** the single next authorized action or blocker, without auto-starting a newly activated phase/scope.

Completion claims must name the exact head SHA and relevant CI/run/review/real-target evidence. A merge, issue comment or generated receipt alone is not proof that the product behavior is correct.