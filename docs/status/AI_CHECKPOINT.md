# WorkIntel AI Checkpoint

This file is the lightweight resumable checkpoint for AI/human engineering sessions. It complements `AGENTS.md`; it is not a substitute for Git, tests, CI, issue authority, or protected-main truth.

## Authority rule

When this file conflicts with observed repository state, use the authority order in `AGENTS.md`:

1. protected `main` and current Git state;
2. `AGENTS.md`;
3. explicit owner-approved GitHub issue/PR scope and canonical repository docs;
4. security/quality/release/migration contracts;
5. this checkpoint as a coordination aid.

Never treat an embedded SHA below as live truth after the repository has moved. Rehydrate the current branch/PR/main before every material write sequence.

## Current checkpoint — 2026-08-31

- **Repository:** `Vertex-Systems-Network/workforce-intelligence`
- **Observed protected main at M14 promotion:** `7f46f9542bbab6fa210a5c9d30acb07d44b91fb4`
- **Product authority state:** `IMPLEMENTING`
- **Current product authority issue:** `#50 NEXT-AUTH — Define post-M13 WorkIntel product authority`
- **Authorized work package:** `M14-RELEASE-TRUST` — Production Release Trust & Real-Target Readiness.
- **Owner approval:** `OWNER APPROVAL: M14-RELEASE-TRUST APPROVED FOR IMPLEMENTATION`, persisted to Issue #50 on 2026-08-31.
- **Canonical M14 specification:** `docs/architecture/M14_RELEASE_TRUST_READINESS.md`.
- **Implementation branch:** `m14/release-trust-readiness`, created from exact protected-main SHA `7f46f9542bbab6fa210a5c9d30acb07d44b91fb4` after the required impact/security/change-budget self-audit.
- **Last accepted completed product milestone:** M13.
- **M14 scope boundary:** release trust/signing/notarization/provenance plus real-target evidence contract only; no new tenant feature, application-role change, database migration, tracking semantic change, payroll/timekeeping change, or M13 canonical ZIP rewrite.
- **M14 risk:** HIGH. Independent review is required. Real-target evidence is required before `PRODUCTION_VERIFIED` / M14 `DONE`.
- **Current implementation direction:** preserve the existing deterministic unsigned PR build lane; add a separate trusted release workflow with no `pull_request` trigger, exact-source binding, fail-closed organization signing/notary credentials, machine-readable final-digest receipts, immutable publication and truthful external-evidence states.
- **Not verified at this checkpoint:** actual organization signing identities/secrets, Windows signed/timestamped release evidence, Apple Developer ID/notary evidence, trusted GitHub release execution, production/release-candidate target, and restore-tested target evidence.
- **Exact next product-safe action:** complete the approved M14 source implementation on the dedicated branch, open a PR, certify the exact final PR head, obtain independent review, merge only the certified head, and keep unavailable external release/real-target evidence explicitly `Not Verified` rather than declaring M14 `DONE`.

## Required session checkpoint fields

After every meaningful engineering session, persist or report the following in the active issue/PR and update this file when the repository-level continuation state materially changes:

- repository;
- authority issue/spec;
- protected-main SHA observed at start;
- working branch;
- exact branch/PR head;
- milestone/module/work package;
- state (`SPECIFICATION`, `AWAITING_DEVELOPMENT_APPROVAL`, `APPROVED`, `IMPLEMENTING`, `VERIFYING`, `BLOCKED`, `PARTIALLY_COMPLETE`, `DONE`);
- completed work;
- changed files/surfaces;
- tests/checks actually executed;
- exact CI/evidence identifiers where applicable;
- baseline failures versus newly introduced failures;
- decisions/ADRs;
- data/migration impact;
- security/privacy/tenant impact;
- known risks;
- not-verified items;
- blockers;
- exact next authorized action.

## Resume protocol

When a user or agent says `continue`/`resume`:

1. fetch current protected `main`;
2. read `AGENTS.md`;
3. read this checkpoint;
4. read the active authority issue/specification;
5. inspect open PRs relevant to the lane;
6. compare the working branch/PR head against current protected `main`;
7. inspect required checks/reviews/unresolved threads;
8. classify the current state;
9. continue only from the safest verified state.

State-specific behavior:

- `SPECIFICATION`: continue documentation/research.
- `AWAITING_DEVELOPMENT_APPROVAL`: do not start product implementation.
- `APPROVED` / `IMPLEMENTING`: continue only the approved scope.
- `VERIFYING`: run/inspect exact-head verification and review.
- `BLOCKED`: resolve the blocker or do genuinely independent authorized work.
- `DONE`: do not silently auto-start a new phase unless repository authority already activates it.

## Evidence discipline

A completion claim must distinguish:

- **Verified:** evidence observed for the exact current SHA/target.
- **Not Verified:** evidence unavailable, stale, not run, or for another target.
- **Known Risk:** unresolved assumption or operational/security/data risk.
- **Next Action:** one explicit authorized continuation step or blocker.

A merge, issue comment, generated receipt, green older SHA, or chat statement is not proof for a newer head.

## Update policy

Update this file only when repository-level continuation state materially changes, for example:

- a new milestone/module gains owner-approved authority;
- the active product authority issue changes;
- a release/milestone closes;
- a material blocker changes the safe next action;
- the repository adopts a different canonical checkpoint mechanism.

Do not update it for every trivial commit. Exact transient SHAs and run IDs belong primarily in the active issue/PR closeout so this file remains lightweight.