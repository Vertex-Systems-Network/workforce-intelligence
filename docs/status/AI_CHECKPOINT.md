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

## Current checkpoint — 2026-08-30

- **Repository:** `Vertex-Systems-Network/workforce-intelligence`
- **Observed protected main at checkpoint creation:** `0b6dc357ba5cec09c5ba9aae8d8acc861737491f`
- **Product authority state:** `AWAITING_PRODUCT_AUTHORITY`
- **Current product authority issue:** `#50 NEXT-AUTH — Define post-M13 WorkIntel product authority`
- **Current governance hardening issue:** `#51 GOV-HARDEN — Complete AI-native module, checkpoint, and operations contracts`
- **Last accepted product milestone:** M13 closeout; no repository-native M14 product implementation authority is active.
- **Dependency maintenance:** PR #39 was merged independently before this checkpoint; do not fold dependency maintenance into future product scope.
- **Known product blocker:** concrete post-M13 milestone/module scope, acceptance criteria, security/data impact and exact starting main must be owner-approved in repository authority before product code starts.
- **Current safe write lane:** documentation/governance hardening authorized by #51 only.
- **Exact next product-safe action:** complete #51 and certify its exact PR head; then return to #50. If #50 still lacks a concrete owner-approved package, remain planning/governance-only.

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